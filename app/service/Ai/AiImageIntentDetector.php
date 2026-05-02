<?php

namespace app\service\Ai;

class AiImageIntentDetector
{
    public function detect(string $content): ?array
    {
        $text = trim($content);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\/(image|img|画图|生成图片)[\s:：]+(.+)$/iu', $text, $match)) {
            return ['prompt' => trim($match[2]), 'source' => 'command'];
        }

        $patterns = [
            '/(?:生成|画|绘制|做)一?张.+(?:图|图片|海报|封面|插画)/u',
            '/(?:帮我|给我).*(?:生成|画|绘制).*(?:图|图片|海报|封面|插画)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return ['prompt' => $text, 'source' => 'natural_language'];
            }
        }

        return null;
    }
}
