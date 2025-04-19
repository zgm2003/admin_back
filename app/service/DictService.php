<?php

namespace app\service;

use app\dep\AddressDep;
use app\dep\AiWorkLine\AiImageVideo\AiImageVideoPromptDep;
use app\dep\AiWorkLine\AiImageVideo\AiImageVideoTaskDep;
use app\dep\Article\ArticleDep;
use app\dep\Article\CategoryDep;
use app\dep\Article\TagDep;
use app\dep\User\RoleDep;
use app\dep\User\PermissionDep;
use app\dep\VoicesDep;
use app\dep\Web\AlbumDep;
use app\dep\Web\MusicDep;
use app\enum\AccountEnum;
use app\enum\AiImageVideoEnum;
use app\enum\ArticleEnum;
use app\enum\CommonEnum;
use app\enum\GoodsEnum;
use app\enum\PinduoduoEnum;
use app\enum\VoicesEnum;


class DictService
{
    public $dict = [];

    public function setIsArr(){
        $this->dict['isArr'] = $this->enumToDict(CommonEnum::$isArr);
        return $this;
    }
    public function setPermissionTree()
    {

        $dep = new PermissionDep();

        $resCategory = $dep->allOK()->map(function ($item) {
            return [
                'id' => $item->id,
                'label' => $item->name,
                'value' => $item->id,
                'parent_id' => $item->parent_id,
            ];
        });
        $this->dict['permission_tree'] = listToTree($resCategory->toArray(), -1);
        return $this;
    }

    public function setAuthAdressTree()
    {

        $dep = new AddressDep();

        $resCategory = $dep->all()->map(function ($item) {
            return [
                'id' => $item->id,
                'label' => $item->name,
                'value' => $item->id,
                'parent_id' => $item->parent_id,
            ];
        });
        $this->dict['auth_address_tree'] = listToTree($resCategory->toArray(), -1);
        return $this;
    }
    public function setRoleArr()
    {
        $roleDep = new RoleDep();
        $res = $roleDep->allOK();
        // 遍历集合并处理每个元素
        $this->dict['roleArr'] = $res->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->name,
            ];
        });
        return $this;
    }

    public function setArticleStatusArr(){
        $this->dict['article_status_arr'] = $this->enumToDict(ArticleEnum::$statusArr);
        return $this;
    }
    public function setArticleTypeArr(){
        $this->dict['article_type_arr'] = $this->enumToDict(ArticleEnum::$typesArr);
        return $this;
    }
    public function setArticleTagArr()
    {
        $dep = new TagDep();
        $res = $dep->allOK();
        // 遍历集合并处理每个元素
        $this->dict['tag_arr'] = $res->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->name,
            ];
        });
        return $this;
    }
    public function setArticleCategoryArr()
    {
        $dep = new CategoryDep();
        $articleDep = new ArticleDep();
        $resArticle = $articleDep->releaseAll();
        $res = $dep->allOK();
        // 遍历集合并处理每个元素
        $this->dict['category_arr'] = $res->map(function ($item) use($resArticle){
            $count = $resArticle->where('category_id',$item['id'])->count();
            return [
                'value' => $item->id,
                'label' => $item->name,
                'icon'  => $item->icon,
                'count' => $count
            ];
        });
        return $this;
    }

    public function setAlbumArr()
    {

        $dep = new AlbumDep();

        $this->dict['album_arr'] = $dep->allOK()->map(function ($item) {
            $imagesList = json_decode($item->images_list);
            $num = is_array($imagesList) ? count($imagesList) : 0;

            return [
                'id' => $item->id,
                'title' => $item->title,
                'desc' => $item->desc,
                'cover' => $item->cover,
                'is_lock' => $item->is_lock,
                'num' => $num
            ];
        });
        return $this;
    }
    public function setMusicArr()
    {
        $musicDep = new MusicDep();
        $res = $musicDep->allOK();
        // 遍历集合并处理每个元素
        $this->dict['musicArr'] = $res->map(function ($item) {
            return [
                'name' => $item->name,
                'artist' => $item->artist,
                'cover' => $item->cover,
                'url' => $item->url,
            ];
        });
        return $this;
    }

    public function setAicategoryArr()
    {
        $categoryDep = new \App\Dep\Ai\CategoryDep();
        $res = $categoryDep->allOK();
        // 遍历集合并处理每个元素
        $this->dict['aiCategoryArr'] = $res->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->name,
                'icon' => $item->icon,
            ];
        });
        return $this;
    }
    public function setVoicesHzArr(){
        $this->dict['hzArr'] = $this->enumToDict(VoicesEnum::$hzArr);
        return $this;
    }
    public function setVoicesQualityArr(){
        $this->dict['qualityArr'] = $this->enumToDict(VoicesEnum::$qualityArr);
        return $this;
    }
    public function setPlatformArr(){
        $this->dict['platform_arr'] = $this->enumToDict(AccountEnum::$platformArr);
        return $this;
    }
    public function setPinduoduoSort()
    {
        $this->dict['pinduoduo_sort'] = $this->enumToDict(PinduoduoEnum::$sortArr);
        return $this;
    }
    public function setGoodsStatusArr(){
        $this->dict['goods_status_arr'] = $this->enumToDict(GoodsEnum::$statusArr);
        return $this;
    }
    public function setGoodsPlatformArr(){
        $this->dict['goods_platform_arr'] = $this->enumToDict(GoodsEnum::$platformArr);
        return $this;
    }
    public function setVoicesArr()
    {
        $dep = new VoicesDep();
        $res = $dep->allOK();
        // 遍历集合并处理每个元素
        $this->dict['voices_arr'] = $res->map(function ($item){
            return [
                'value' => $item->id,
                'label' => $item->name.'-'.$item->code,
            ];
        });
        return $this;
    }

    public function setAiImageVideoPlatformArr(){
        $this->dict['ai_image_video_platform_arr'] = $this->enumToDict(AiImageVideoEnum::$platformArr);
        return $this;
    }
    public function setAiImageVideoImageSizeArr(){
        $this->dict['image_size_arr'] = $this->enumToDict(AiImageVideoEnum::$imageSizeArr);
        return $this;
    }
    public function setAiImageVideoPromptArr()
    {
        $dep = new AiImageVideoPromptDep();
        $res = $dep->allOK();
        // 遍历集合并处理每个元素
        $this->dict['ai_image_video_prompt_arr'] = $res->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->title,
            ];
        });
        return $this;
    }
    public function setAiImageVideoTaskNameArr()
    {
        $dep = new AiImageVideoTaskDep();
        $res = $dep->allOK();
        // 遍历集合并处理每个元素
        $this->dict['ai_image_video_task_name_arr'] = $res->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->name,
            ];
        });
        return $this;
    }
    public function setAiImageVideoTaskStatusArr(){
        $this->dict['ai_image_video_task_status_arr'] = $this->enumToDict(AiImageVideoEnum::$taskStatusArr);
        return $this;
    }
    public function setAiImageVideoStatusArr(){
        $this->dict['ai_image_video_status_arr'] = $this->enumToDict(AiImageVideoEnum::$statusArr);
        return $this;
    }
    public function setBlogTagArr()
    {
        $dep = new TagDep();
        $articleDep = new ArticleDep();

        // 获取标签集合和文章集合
        $res = collect($dep->allOK());
        $resArticle = collect($articleDep->releaseAll());

        // 遍历标签集合并处理每个元素
        $this->dict['tag_arr'] = $res->map(function ($item) use ($resArticle) {
            $articleNum = $resArticle->filter(function ($article) use ($item) {
                return in_array($item['id'], json_decode($article['tag_id'], true));
            })->count();

            return [
                'value' => $item['id'],
                'label' => $item['name'],
                'count' => $articleNum,
            ];
        });

        // 添加 "全部" 选项，统计所有文章的总数
        $this->dict['tag_arr']->prepend([
            'value' => 0,
            'label' => '全部',
            'count' => $resArticle->count(), // 使用文章集合统计总数
        ]);

        return $this;
    }
    public function setBlogCategoryArr()
    {
        $dep = new \App\Dep\Article\CategoryDep();
        $articleDep = new ArticleDep();
        $resArticle = $articleDep->releaseAll();
        $res = $dep->allOK();
        // 遍历集合并处理每个元素
        $this->dict['category_arr'] = $res->map(function ($item) use($resArticle){
            $count = $resArticle->where('category_id',$item['id'])->count();
            return [
                'value' => $item->id,
                'label' => $item->name,
                'icon'  => $item->icon,
                'count' => $count
            ];
        });
        $this->dict['category_arr']->prepend([
            'value' => 0,
            'label' => '全部',
            'icon'  => 'folder-opened',
            'count' => $resArticle->count(),
        ]);
        return $this;
    }
    public function setCarouselArticlesArr()
    {
        $dep = new ArticleDep();
        $res = $dep->carousel();
        // 遍历集合并处理每个元素
        $this->dict['carousel_arr'] = $res->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'cover'  => $item->cover,
            ];
        });
        return $this;
    }
    public function enumToDict($enum)
    {
        $res = [];
        foreach ($enum as $index => $item) {
            $res[] = [
                'label' => $item,
                'value' => $index,
            ];
        }
        return $res;
    }
    public function getDict()
    {
        return $this->dict;
    }
}
