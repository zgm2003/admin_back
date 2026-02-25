<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\GoodsDep;
use app\enum\CommonEnum;
use app\enum\GoodsEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\Ai\GoodsValidate;

/**
 * 电商商品口播词管理模块
 * 负责：商品 CRUD、插件提交、OCR识别、AI生成口播词、TTS语音合成
 * 流水线：待处理 → OCR中 → OCR完成 → 生成中 → 生成完成 → TTS中 → TTS完成
 * 状态流转使用乐观锁 + Redis 异步队列
 */
class GoodsModule extends BaseModule
{
    /** @var array 平台域名 → 枚举映射 */
    private const PLATFORM_MAP = [
        'item.taobao.com'          => GoodsEnum::PLATFORM_TAOBAO,
        'item.jd.com'              => GoodsEnum::PLATFORM_JD,
        'detail.tmall.com'         => GoodsEnum::PLATFORM_TMALL,
        'chaoshi.detail.tmall.com' => GoodsEnum::PLATFORM_TMALL_CHAOSHI,
        'yangkeduo.com'            => GoodsEnum::PLATFORM_PDD,
        'mobile.yangkeduo.com'     => GoodsEnum::PLATFORM_PDD,
    ];

    // ==================== 查询类 ====================

    /**
     * 初始化（返回平台、状态、音色字典 + 商品口播专用智能体列表）
     */
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setGoodsPlatformArr()
            ->setGoodsStatusArr()
            ->setGoodsVoiceArr()
            ->setGoodsEmotionArr()
            ->getDict();

        // 商品口播专用智能体列表
        $agents = $this->dep(AiAgentsDep::class)->getActiveByScene('goods_script');
        $data['dict']['goods_agent_list'] = $agents->map(fn($item) => [
            'value' => $item->id,
            'label' => $item->name,
        ])->toArray();

        return self::success($data);
    }

    /**
     * 各状态数量统计（用于前端 tab 角标）
     */
    public function statusCount($request): array
    {
        $title    = $request->input('title', '');
        $platform = $request->input('platform');

        $countMap = $this->dep(GoodsDep::class)->statusCount(
            $title ?: null,
            ($platform !== null && $platform !== '') ? (int)$platform : null
        );

        $total  = array_sum($countMap);
        $result = [['label' => '全部', 'value' => '', 'num' => $total]];
        foreach (GoodsEnum::$statusArr as $val => $label) {
            $result[] = ['label' => $label, 'value' => $val, 'num' => $countMap[$val] ?? 0];
        }

        return self::success($result);
    }

    /**
     * 商品列表（分页，含平台名称、状态名称）
     */
    public function list($request): array
    {
        $param = $this->validate($request, GoodsValidate::list());
        $res   = $this->dep(GoodsDep::class)->list($param);

        $list = $res->map(fn($item) => [
            'id'                 => $item->id,
            'title'              => $item->title,
            'main_img'           => $item->main_img,
            'platform'           => $item->platform,
            'platform_name'      => GoodsEnum::$platformArr[$item->platform] ?? '未知',
            'link'               => $item->link,
            'tips'               => $item->tips,
            'ocr'                => $item->ocr,
            'point'              => $item->point,
            'script_text'        => $item->script_text,
            'model_origin'       => $item->model_origin,
            'status'             => $item->status,
            'status_name'        => GoodsEnum::$statusArr[$item->status] ?? '',
            'status_msg'         => $item->status_msg,
            'image_list'         => $item->image_list,
            'image_list_success' => $item->image_list_success,
            'audio_url'          => $item->audio_url,
            'meta'               => $item->meta,
            'created_at'         => $item->created_at,
            'updated_at'         => $item->updated_at,
        ]);

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    // ==================== 写入类 ====================

    /**
     * 新增商品（手动录入）
     */
    public function add($request): array
    {
        $param = $this->validate($request, GoodsValidate::add());

        $data = [
            'title'      => $param['title'] ?? '',
            'main_img'   => $param['main_img'] ?? '',
            'platform'   => (int)($param['platform'] ?? -1),
            'link'       => $param['link'] ?? '',
            'image_list' => !empty($param['image_list']) ? \json_encode($param['image_list']) : null,
            'status'     => GoodsEnum::STATUS_PENDING,
            'is_del'     => CommonEnum::NO,
        ];

        $id = $this->dep(GoodsDep::class)->add($data);
        return self::success(['id' => $id]);
    }

    /**
     * 编辑商品（部分更新，JSON 字段序列化）
     */
    public function edit($request): array
    {
        $param = $this->validate($request, GoodsValidate::edit());
        $id    = (int)$param['id'];
        $dep   = $this->dep(GoodsDep::class);

        $dep->getOrFail($id);

        $data   = [];
        $fields = ['title', 'main_img', 'link', 'tips', 'point', 'script_text'];
        foreach ($fields as $field) {
            if (isset($param[$field])) {
                $data[$field] = $param[$field];
            }
        }
        if (isset($param['image_list'])) {
            $data['image_list'] = \json_encode($param['image_list']);
        }
        if (isset($param['image_list_success'])) {
            $data['image_list_success'] = \json_encode($param['image_list_success']);
        }
        if (isset($param['meta'])) {
            $data['meta'] = \json_encode($param['meta']);
        }

        if (!empty($data)) {
            $dep->update($id, $data);
        }

        return self::success();
    }

    /**
     * 删除商品（支持批量，软删除）
     */
    public function del($request): array
    {
        $param    = $this->validate($request, GoodsValidate::del());
        $affected = $this->dep(GoodsDep::class)->delete($param['id']);
        return self::success(['affected' => $affected]);
    }

    /**
     * 插件提交商品数据
     */
    public function submit($request): array
    {
        $param = $this->validate($request, GoodsValidate::submit());

        $platformId = $this->resolvePlatform($param['platform'] ?? '');
        $images     = $param['images'] ?? [];

        $data = [
            'title'      => $param['title'] ?? '',
            'main_img'   => $images[0] ?? '',
            'platform'   => $platformId,
            'link'       => $param['link'] ?? '',
            'image_list' => \json_encode($images),
            'meta'       => !empty($param['meta']) ? \json_encode($param['meta']) : null,
            'status'     => GoodsEnum::STATUS_PENDING,
            'is_del'     => CommonEnum::NO,
        ];

        $id = $this->dep(GoodsDep::class)->add($data);
        return self::success(['id' => $id]);
    }

    // ==================== 流水线操作（状态校验 + 入队列） ====================

    /**
     * OCR识别（异步）
     */
    public function ocr($request): array
    {
        $param = $this->validate($request, GoodsValidate::ocr());
        $id    = (int)$param['id'];
        $dep   = $this->dep(GoodsDep::class);
        $goods = $dep->getOrFail($id);

        $images = $param['image_list_success'] ?? $goods->image_list_success ?? $goods->image_list ?? [];
        self::throwIf(empty($images), '没有可识别的图片');

        // 乐观锁切状态 → OCR中
        $affected = $dep->transitStatus($id, $goods->status, GoodsEnum::STATUS_OCR, [
            'image_list_success' => \json_encode($images),
        ]);
        self::throwIf($affected === 0, '状态已变更，请刷新后重试');

        // 入队列
        \Webman\RedisQueue\Client::send('goods_process', [
            'id'                 => $id,
            'step'               => 'ocr',
            'image_list_success' => $images,
        ]);

        return self::success(['msg' => 'OCR任务已提交']);
    }

    /**
     * AI生成口播词（异步）
     */
    public function generate($request): array
    {
        $param = $this->validate($request, GoodsValidate::generate());
        $id    = (int)$param['id'];
        $dep   = $this->dep(GoodsDep::class);
        $goods = $dep->getOrFail($id);

        $ocrText = $goods->ocr ?? '';
        $title   = $goods->title ?? '';
        self::throwIf(empty($ocrText) && empty($title), '请先进行OCR识别或填写商品标题');

        // 乐观锁切状态 → 生成中
        $extra = [];
        if (!empty($param['tips'])) {
            $extra['tips'] = $param['tips'];
        }
        $affected = $dep->transitStatus($id, $goods->status, GoodsEnum::STATUS_GENERATING, $extra);
        self::throwIf($affected === 0, '状态已变更，请刷新后重试');

        // 入队列
        \Webman\RedisQueue\Client::send('goods_process', [
            'id'       => $id,
            'step'     => 'generate',
            'agent_id' => (int)$param['agent_id'],
            'tips'     => $param['tips'] ?? '',
        ]);

        return self::success(['msg' => '生成任务已提交']);
    }

    /**
     * TTS语音合成（异步）
     */
    public function tts($request): array
    {
        $param = $this->validate($request, GoodsValidate::tts());
        $id    = (int)$param['id'];
        $voice = $param['voice'] ?? GoodsEnum::VOICE_XIAOXIAO;
        $dep   = $this->dep(GoodsDep::class);
        $goods = $dep->getOrFail($id);

        $scriptText = $param['script_text'] ?? $goods->script_text ?? '';
        self::throwIf(empty($scriptText), '没有口播词内容，请先生成口播词');

        // 乐观锁切状态 → TTS中
        $extra = [];
        if (!empty($param['script_text'])) {
            $extra['script_text'] = $param['script_text'];
        }
        $affected = $dep->transitStatus($id, $goods->status, GoodsEnum::STATUS_TTS, $extra);
        self::throwIf($affected === 0, '状态已变更，请刷新后重试');

        // 入队列
        \Webman\RedisQueue\Client::send('goods_process', [
            'id'          => $id,
            'step'        => 'tts',
            'voice'       => $voice,
            'emotion'     => $param['emotion'] ?? GoodsEnum::EMOTION_DEFAULT,
            'script_text' => $scriptText,
        ]);

        return self::success(['msg' => 'TTS任务已提交']);
    }

    // ==================== 私有方法 ====================

    /**
     * 解析平台域名 → 枚举值
     */
    private function resolvePlatform(string $host): int
    {
        foreach (self::PLATFORM_MAP as $domain => $enumVal) {
            if (\str_contains($host, $domain)) {
                return $enumVal;
            }
        }
        return -1;
    }
}
