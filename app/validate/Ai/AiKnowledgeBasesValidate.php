<?php

namespace app\validate\Ai;

use app\enum\AiEnum;
use app\enum\CommonEnum;
use Respect\Validation\Validator as v;

class AiKnowledgeBasesValidate
{
    public static function list(): array
    {
        return [
            'page_size' => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page' => v::optional(v::intVal()->positive()),
            'name' => v::optional(v::stringType()),
            'visibility' => v::optional(v::in(array_keys(AiEnum::$knowledgeVisibilityArr))),
            'status' => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr))),
        ];
    }

    public static function add(): array
    {
        return [
            'name' => v::stringType()->length(1, 80)->setName('知识库名称'),
            'description' => v::optional(v::stringType()->length(0, 500))->setName('描述'),
            'visibility' => v::optional(v::in(array_keys(AiEnum::$knowledgeVisibilityArr)))->setName('可见性'),
            'permission_json' => v::optional(v::arrayType())->setName('权限配置'),
            'chunk_size' => v::optional(v::intVal()->between(100, 4000))->setName('切片长度'),
            'chunk_overlap' => v::optional(v::intVal()->between(0, 1000))->setName('切片重叠'),
            'top_k' => v::optional(v::intVal()->between(1, 20))->setName('召回数量'),
            'score_threshold' => v::optional(v::floatVal()->between(0, 100))->setName('分数阈值'),
            'status' => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
            'name' => v::optional(v::stringType()->length(1, 80))->setName('知识库名称'),
            'description' => v::optional(v::stringType()->length(0, 500))->setName('描述'),
            'visibility' => v::optional(v::in(array_keys(AiEnum::$knowledgeVisibilityArr)))->setName('可见性'),
            'permission_json' => v::optional(v::arrayType())->setName('权限配置'),
            'chunk_size' => v::optional(v::intVal()->between(100, 4000))->setName('切片长度'),
            'chunk_overlap' => v::optional(v::intVal()->between(0, 1000))->setName('切片重叠'),
            'top_k' => v::optional(v::intVal()->between(1, 20))->setName('召回数量'),
            'score_threshold' => v::optional(v::floatVal()->between(0, 100))->setName('分数阈值'),
            'status' => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
        ];
    }

    public static function detail(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal()->positive(), v::arrayType()->each(v::intVal()->positive()))->setName('ID'),
        ];
    }

    public static function status(): array
    {
        return [
            'id' => v::oneOf(v::intVal()->positive(), v::arrayType()->each(v::intVal()->positive()))->setName('ID'),
            'status' => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('状态'),
        ];
    }

    public static function documents(): array
    {
        return [
            'knowledge_base_id' => v::intVal()->positive()->setName('知识库ID'),
            'page_size' => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page' => v::optional(v::intVal()->positive()),
            'title' => v::optional(v::stringType()),
            'status' => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr))),
        ];
    }

    public static function addDocument(): array
    {
        return [
            'knowledge_base_id' => v::intVal()->positive()->setName('知识库ID'),
            'title' => v::stringType()->length(1, 120)->setName('文档标题'),
            'source_type' => v::optional(v::in([AiEnum::KNOWLEDGE_SOURCE_MANUAL, AiEnum::KNOWLEDGE_SOURCE_TEXT]))->setName('来源类型'),
            'content' => v::stringType()->notEmpty()->setName('文档内容'),
            'status' => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
        ];
    }

    public static function editDocument(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('文档ID'),
            'knowledge_base_id' => v::intVal()->positive()->setName('知识库ID'),
            'title' => v::optional(v::stringType()->length(1, 120))->setName('文档标题'),
            'source_type' => v::optional(v::in([AiEnum::KNOWLEDGE_SOURCE_MANUAL, AiEnum::KNOWLEDGE_SOURCE_TEXT]))->setName('来源类型'),
            'content' => v::optional(v::stringType()->notEmpty())->setName('文档内容'),
            'status' => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
        ];
    }

    public static function documentDetail(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('文档ID'),
            'knowledge_base_id' => v::intVal()->positive()->setName('知识库ID'),
        ];
    }

    public static function delDocument(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('文档ID'),
            'knowledge_base_id' => v::intVal()->positive()->setName('知识库ID'),
        ];
    }

    public static function reindexDocument(): array
    {
        return self::delDocument();
    }

    public static function chunks(): array
    {
        return [
            'knowledge_base_id' => v::intVal()->positive()->setName('知识库ID'),
            'document_id' => v::optional(v::intVal()->positive())->setName('文档ID'),
            'page_size' => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page' => v::optional(v::intVal()->positive()),
        ];
    }

    public static function retrievalTest(): array
    {
        return [
            'knowledge_base_id' => v::intVal()->positive()->setName('知识库ID'),
            'query' => v::stringType()->notEmpty()->setName('查询内容'),
            'top_k' => v::optional(v::intVal()->between(1, 20))->setName('召回数量'),
        ];
    }
}
