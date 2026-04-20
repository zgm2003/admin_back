<?php

namespace tests\System;

use app\dep\Ai\GoodsDep;
use app\enum\CommonEnum;
use app\enum\GoodsEnum;
use app\model\Ai\GoodsModel;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class GoodsDepJsonCastContractTest extends TestCase
{
    private static Capsule $capsule;

    public static function setUpBeforeClass(): void
    {
        self::$capsule = new Capsule();
        self::$capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        self::$capsule->setAsGlobal();
        self::$capsule->bootEloquent();

        GoodsModel::setConnectionResolver(self::$capsule->getDatabaseManager());

        self::$capsule->schema()->create('goods', function ($table) {
            $table->increments('id');
            $table->string('title')->default('');
            $table->string('main_img')->default('');
            $table->integer('platform')->default(-1);
            $table->text('link')->nullable();
            $table->text('image_list')->nullable();
            $table->text('image_list_success')->nullable();
            $table->text('meta')->nullable();
            $table->integer('status')->default(GoodsEnum::STATUS_PENDING);
            $table->text('status_msg')->nullable();
            $table->integer('is_del')->default(CommonEnum::NO);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    protected function setUp(): void
    {
        self::$capsule->table('goods')->truncate();
    }

    public function testAddSerializesJsonCastColumnsWhenArraysAreProvided(): void
    {
        $dep = new GoodsDep();

        $id = $dep->add([
            'title' => 'test goods',
            'main_img' => 'https://example.com/a.jpg',
            'platform' => GoodsEnum::PLATFORM_TMALL,
            'link' => 'https://example.com/item',
            'image_list' => ['https://example.com/1.jpg', 'https://example.com/2.jpg'],
            'meta' => ['sales' => '300+', 'description' => 'desc'],
            'status' => GoodsEnum::STATUS_PENDING,
            'is_del' => CommonEnum::NO,
        ]);

        $row = GoodsModel::query()->findOrFail($id);

        self::assertSame(['https://example.com/1.jpg', 'https://example.com/2.jpg'], $row->image_list);
        self::assertSame(['sales' => '300+', 'description' => 'desc'], $row->meta);
    }

    public function testUpdateAndTransitStatusSerializeJsonCastColumnsWhenArraysAreProvided(): void
    {
        $id = GoodsModel::query()->insertGetId([
            'title' => 'test goods',
            'main_img' => '',
            'platform' => GoodsEnum::PLATFORM_TMALL,
            'link' => '',
            'image_list' => json_encode(['https://example.com/1.jpg'], JSON_UNESCAPED_UNICODE),
            'meta' => json_encode(['sales' => '100+'], JSON_UNESCAPED_UNICODE),
            'status' => GoodsEnum::STATUS_PENDING,
            'is_del' => CommonEnum::NO,
        ]);

        $dep = new GoodsDep();

        $dep->update($id, [
            'image_list_success' => ['https://example.com/ok.jpg'],
        ]);

        $dep->transitStatus($id, GoodsEnum::STATUS_PENDING, GoodsEnum::STATUS_OCR, [
            'image_list_success' => ['https://example.com/final.jpg'],
        ]);

        $row = GoodsModel::query()->findOrFail($id);

        self::assertSame(['https://example.com/final.jpg'], $row->image_list_success);
        self::assertSame(GoodsEnum::STATUS_OCR, $row->status);
    }
}
