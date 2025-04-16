<?php

namespace app\queue\redis\slow\article;

use app\dep\Article\ArticleDep;
use app\dep\Article\CategoryDep;
use app\dep\Article\TagDep;
use app\enum\ArticleEnum;
use app\lib\AliCloud\AigcSdk;
use Webman\RedisQueue\Consumer;

class ArticleModel implements Consumer
{
    // 要消费的队列名
    public $queue = 'article-model';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';
    public $contentId;

    // 消费
    public function consume($data)
    {
        $this->contentId = $data;
        $dep = new ArticleDep();
        $tagDep = new TagDep();
        $categoryDep = new CategoryDep();

        $item = $dep->first($this->contentId);

        if (!$item) {
            $this->log('没找到该ID内容: ' . $this->contentId);
            return false;
        }

        $tagsId = json_decode($item->tag_id, true);
        $tagName = [];
        foreach ($tagsId as $tagId) {
            $tag = $tagDep->first($tagId);
            $tagName[] = $tag['name'];
        }

        $resCategory = $categoryDep->first($item->category_id);

        $sdk = new AigcSdk();


        $prompt = $item->prompt;

        $chat = str_replace('{title}', $item['title'], $prompt);
        $chat = str_replace('{desc}', $item['desc'], $chat);
        $chat = str_replace('{tag}', json_encode($tagName), $chat);
        $chat = str_replace('{category}', $resCategory->name, $chat);

        $resChat = $sdk->chat("我是一名CSDN上的博客博主", $chat);
        $origin = $resChat['output']['choices'][0]['message']['content'];

        $this->log("AI response: {$origin}");

        $content = trim(str_replace(['```json', '```'], '', $origin));
        $contentArr = json_decode($content, true);

        if (!isset($contentArr['内容'])) {
            $data = [
                'status' => ArticleEnum::MODEL_ERROR,
                'status_msg' => 'AI生成不符合预期',
            ];
            $dep->edit($this->contentId, $data);
            $this->log('Content generation error', ['id' => $this->contentId]);
            return true;
        }
        $data = [
            'status' => ArticleEnum::REVIEW,
            'content' => $contentArr['内容'],
        ];
        $dep->edit($this->contentId, $data);


        return true;
    }

    public function onConsumeFailure(\Throwable $e, $package)
    {
        $dep = new ArticleDep();
        $data = [
            'status' => ArticleEnum::MODEL_ERROR,
            'status_msg' => $e->getMessage(),
        ];

        $dep->edit($this->contentId, $data);
    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("queue_" . $this->queue); // 获取 Logger 实例
        $logger->info($msg, $context);
    }

}
