<?php

namespace app\service\DevTools;

/**
 * 代码生成器核心服务
 */
class CodeGenerator
{
    private array $config;
    private string $moduleName;
    private string $moduleNameLower;
    private string $tableName;
    private string $domain;
    private string $domainLower;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->moduleName = $config['module_name'];
        $this->moduleNameLower = lcfirst($this->moduleName);
        $this->tableName = $config['table_name'];
        $this->domain = $config['domain'] ?? 'System';
        $this->domainLower = lcfirst($this->domain);
    }

    /**
     * 预览生成的代码
     */
    public function preview(): array
    {
        return [
            'controller' => [
                'path' => "app/controller/{$this->domain}/{$this->moduleName}Controller.php",
                'content' => $this->generateController(),
            ],
            'module' => [
                'path' => "app/module/{$this->domain}/{$this->moduleName}Module.php",
                'content' => $this->generateModule(),
            ],
            'dep' => [
                'path' => "app/dep/{$this->domain}/{$this->moduleName}Dep.php",
                'content' => $this->generateDep(),
            ],
            'validate' => [
                'path' => "app/validate/{$this->domain}/{$this->moduleName}Validate.php",
                'content' => $this->generateValidate(),
            ],
            'route' => [
                'path' => "routes/admin.php (手动添加)",
                'content' => $this->generateRoute(),
            ],
            'api' => [
                'path' => "src/api/{$this->domainLower}/{$this->moduleNameLower}.ts",
                'content' => $this->generateApi(),
            ],
            'vue' => [
                'path' => "src/views/Main/{$this->domainLower}/{$this->moduleNameLower}/index.vue",
                'content' => $this->generateVue(),
            ],
        ];
    }

    /**
     * 生成文件
     */
    public function generate(): array
    {
        $files = $this->preview();
        $created = [];
        $skipped = [];

        $backendBase = base_path();
        $frontendBase = dirname($backendBase) . '/admin_front_ts';

        foreach ($files as $key => $file) {
            // route 不生成文件，只提示
            if ($key === 'route') continue;
            
            if (in_array($key, ['api', 'vue'])) {
                $fullPath = $frontendBase . '/' . $file['path'];
            } else {
                $fullPath = $backendBase . '/' . $file['path'];
            }

            // 检查文件是否存在
            if (file_exists($fullPath)) {
                $skipped[] = $file['path'];
                continue;
            }

            // 创建目录
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 写入文件
            file_put_contents($fullPath, $file['content']);
            $created[] = $file['path'];
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * 生成 Controller
     */
    private function generateController(): string
    {
        return <<<PHP
<?php

namespace app\controller\\{$this->domain};

use app\controller\Controller;
use app\module\\{$this->domain}\\{$this->moduleName}Module;
use support\Request;

class {$this->moduleName}Controller extends Controller
{
    public function init(Request \$request) { return \$this->run([{$this->moduleName}Module::class, 'init'], \$request); }
    public function list(Request \$request) { return \$this->run([{$this->moduleName}Module::class, 'list'], \$request); }

    /** @OperationLog("{$this->config['menu_name']}新增") @Permission("{$this->moduleNameLower}.add") */
    public function add(Request \$request) { return \$this->run([{$this->moduleName}Module::class, 'add'], \$request); }

    /** @OperationLog("{$this->config['menu_name']}编辑") @Permission("{$this->moduleNameLower}.edit") */
    public function edit(Request \$request) { return \$this->run([{$this->moduleName}Module::class, 'edit'], \$request); }

    /** @OperationLog("{$this->config['menu_name']}删除") @Permission("{$this->moduleNameLower}.del") */
    public function del(Request \$request) { return \$this->run([{$this->moduleName}Module::class, 'del'], \$request); }
}
PHP;
    }

    /**
     * 生成 Module
     */
    private function generateModule(): string
    {
        $searchFields = $this->getSearchFields();
        $formFields = $this->getFormFields();
        $listFields = $this->getListFields();

        $searchConditions = $this->buildSearchConditions($searchFields);
        $listMapping = $this->buildListMapping($listFields);

        return <<<PHP
<?php

namespace app\module\\{$this->domain};

use app\dep\\{$this->domain}\\{$this->moduleName}Dep;
use app\module\BaseModule;
use app\validate\\{$this->domain}\\{$this->moduleName}Validate;

class {$this->moduleName}Module extends BaseModule
{
    protected {$this->moduleName}Dep \${$this->moduleNameLower}Dep;

    public function __construct()
    {
        \$this->{$this->moduleNameLower}Dep = new {$this->moduleName}Dep();
    }

    public function init(\$request): array
    {
        return self::success([
            'dict' => []
        ]);
    }

    public function list(\$request): array
    {
        \$param = \$request->all();
        \$param['page_size'] = \$param['page_size'] ?? 20;
        \$param['current_page'] = \$param['current_page'] ?? 1;
        
        \$res = \$this->{$this->moduleNameLower}Dep->list(\$param);
        
        \$list = \$res->map(function (\$item) {
            return [
{$listMapping}
            ];
        });
        
        \$page = [
            'page_size' => \$param['page_size'],
            'current_page' => \$param['current_page'],
            'total_page' => \$res->lastPage(),
            'total' => \$res->total(),
        ];
        
        return self::paginate(\$list, \$page);
    }

    public function add(\$request): array
    {
        \$param = \$this->validate(\$request, {$this->moduleName}Validate::add());
        
        \$this->{$this->moduleNameLower}Dep->add(\$param);
        
        return self::success();
    }

    public function edit(\$request): array
    {
        \$param = \$this->validate(\$request, {$this->moduleName}Validate::edit());
        
        \$row = \$this->{$this->moduleNameLower}Dep->find(\$param['id']);
        self::throwNotFound(\$row);
        
        \$this->{$this->moduleNameLower}Dep->update(\$param['id'], \$param);
        
        return self::success();
    }

    public function del(\$request): array
    {
        \$param = \$this->validate(\$request, {$this->moduleName}Validate::del());
        
        \$this->{$this->moduleNameLower}Dep->delete(\$param['id']);
        
        return self::success();
    }
}
PHP;
    }

    /**
     * 生成 Dep
     */
    private function generateDep(): string
    {
        $searchFields = $this->getSearchFields();
        $searchWhen = $this->buildDepSearchWhen($searchFields);
        $modelClass = $this->guessModelClass();

        return <<<PHP
<?php

namespace app\dep\\{$this->domain};

use app\dep\BaseDep;
{$modelClass['import']}
use app\enum\CommonEnum;
use support\Model;

class {$this->moduleName}Dep extends BaseDep
{
    protected function createModel(): Model
    {
        return new {$modelClass['name']}();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array \$param)
    {
        return \$this->model
{$searchWhen}
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'desc')
            ->paginate(\$param['page_size'], ['*'], 'page', \$param['current_page']);
    }
}
PHP;
    }

    /**
     * 生成 Validate
     */
    private function generateValidate(): string
    {
        $formFields = $this->getFormFields();
        $addRules = $this->buildValidateRules($formFields, 'add');
        $editRules = $this->buildValidateRules($formFields, 'edit');

        return <<<PHP
<?php

namespace app\validate\\{$this->domain};

class {$this->moduleName}Validate
{
    public static function add(): array
    {
        return [
{$addRules}
        ];
    }

    public static function edit(): array
    {
        return [
            'id' => 'required|integer',
{$editRules}
        ];
    }

    public static function del(): array
    {
        return [
            'id' => 'required|integer',
        ];
    }
}
PHP;
    }

    /**
     * 生成路由配置
     */
    private function generateRoute(): string
    {
        return <<<PHP
    // {$this->moduleName} - {$this->config['menu_name']}
    Route::post('/{$this->domain}/{$this->moduleName}/init', [controller\\{$this->domain}\\{$this->moduleName}Controller::class, 'init']);
    Route::post('/{$this->domain}/{$this->moduleName}/list', [controller\\{$this->domain}\\{$this->moduleName}Controller::class, 'list']);
    Route::post('/{$this->domain}/{$this->moduleName}/add', [controller\\{$this->domain}\\{$this->moduleName}Controller::class, 'add']);
    Route::post('/{$this->domain}/{$this->moduleName}/edit', [controller\\{$this->domain}\\{$this->moduleName}Controller::class, 'edit']);
    Route::post('/{$this->domain}/{$this->moduleName}/del', [controller\\{$this->domain}\\{$this->moduleName}Controller::class, 'del']);
PHP;
    }

    /**
     * 生成前端 API
     */
    private function generateApi(): string
    {
        return <<<TS
import request from '@/utils/request'

export const {$this->moduleName}Api = {
  init: (params?: any) => request.post('/api/admin/{$this->domain}/{$this->moduleName}/init', params),
  list: (params: any) => request.post('/api/admin/{$this->domain}/{$this->moduleName}/list', params),
  add: (params: any) => request.post('/api/admin/{$this->domain}/{$this->moduleName}/add', params),
  edit: (params: any) => request.post('/api/admin/{$this->domain}/{$this->moduleName}/edit', params),
  del: (params: any) => request.post('/api/admin/{$this->domain}/{$this->moduleName}/del', params)
}
TS;
    }

    /**
     * 生成前端 Vue 页面
     */
    private function generateVue(): string
    {
        $listFields = $this->getListFields();
        $searchFields = $this->getSearchFields();
        $formFields = $this->getFormFields();

        $searchFieldsCode = $this->buildVueSearchFields($searchFields);
        $columnsCode = $this->buildVueColumns($listFields);
        $formItemsCode = $this->buildVueFormItems($formFields);
        $formDataCode = $this->buildVueFormData($formFields);
        $formRulesCode = $this->buildVueFormRules($formFields);

        return <<<VUE
<script setup lang="ts">
import {ref, computed, onMounted, nextTick} from 'vue'
import {useI18n} from 'vue-i18n'
import {{$this->moduleName}Api} from '@/api/{$this->domainLower}/{$this->moduleNameLower}'
import {useIsMobile} from '@/hooks/useResponsive'
import {ElNotification} from 'element-plus'
import type {FormInstance, FormRules} from 'element-plus'
import {AppTable} from '@/components/Table'
import {Search} from '@/components/Search'
import type {SearchField} from '@/components/Search/types'
import {useUserStore} from '@/store/user'
import {useTable} from '@/hooks/useTable'

const {t} = useI18n()
const isMobile = useIsMobile()
const userStore = useUserStore()
const dict = ref({} as any)

const searchForm = ref({})

const {
  loading: listLoading,
  data: listData,
  page,
  selectedIds,
  onSearch,
  onPageChange,
  refresh,
  getList,
  onSelectionChange,
  confirmDel,
  batchDel
} = useTable({
  api: {$this->moduleName}Api,
  searchForm
})

const dialogVisible = ref(false)
const dialogMode = ref<'add' | 'edit'>('add')

const form = ref({
{$formDataCode}
})

const formRef = ref<FormInstance | null>(null)
const rules = computed<FormRules>(() => ({
{$formRulesCode}
}))

const init = () => {
  {$this->moduleName}Api.init()
    .then((data: any) => {
      dict.value = data.dict || {}
    })
    .catch(() => {})
}

const searchFields = computed<SearchField[]>(() => [
{$searchFieldsCode}
])

const columns = computed(() => [
{$columnsCode}
  {key: 'actions', label: t('common.actions.action'), width: 220}
])

const add = () => {
  dialogMode.value = 'add'
  form.value = {
{$formDataCode}
  }
  dialogVisible.value = true
  nextTick(() => {
    formRef.value?.clearValidate()
  })
}

const edit = (row: any) => {
  dialogMode.value = 'edit'
  form.value = {...row}
  dialogVisible.value = true
  nextTick(() => {
    formRef.value?.clearValidate()
  })
}

const confirmSubmit = async () => {
  if (!formRef.value) return
  try {
    await formRef.value?.validate()
  } catch {
    return
  }
  
  const api = dialogMode.value === 'add' ? {$this->moduleName}Api.add : {$this->moduleName}Api.edit
  api(form.value).then(() => {
    ElNotification.success({message: t('common.success.operation')})
    dialogVisible.value = false
    getList()
  })
}

onMounted(() => {
  init()
  getList()
})
</script>

<template>
  <div class="box">
    <Search v-model="searchForm" :fields="searchFields" @query="onSearch" @reset="onSearch"/>
    <div class="table">
      <AppTable
        :columns="columns"
        :data="listData"
        :loading="listLoading"
        row-key="id"
        :pagination="page"
        selectable
        :show-index="true"
        @refresh="refresh"
        @update:pagination="onPageChange"
        @selection-change="onSelectionChange"
      >
        <template #toolbar-left>
          <el-button type="success" @click="add" v-if="userStore.can('{$this->moduleNameLower}.add')">{{ t('common.actions.add') }}</el-button>
          <el-dropdown>
            <el-button type="primary">
              {{ t('common.actions.batchAction') }}
              <el-icon class="el-icon--right">
                <arrow-right/>
              </el-icon>
            </el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item @click="batchDel" v-if="userStore.can('{$this->moduleNameLower}.del')">{{ t('common.actions.batchDelete') }}</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </template>
        <template #cell-actions="{ row }">
          <el-button type="primary" text @click="edit(row)" v-if="userStore.can('{$this->moduleNameLower}.edit')">{{ t('common.actions.edit') }}</el-button>
          <el-button type="danger" text @click="confirmDel(row)" v-if="userStore.can('{$this->moduleNameLower}.del')">{{ t('common.actions.del') }}</el-button>
        </template>
      </AppTable>
    </div>
  </div>

  <el-dialog v-model="dialogVisible" :width="isMobile ? '94vw' : '900px'">
    <template #header>{{ dialogMode === 'add' ? '新增' : '编辑' }}</template>
    <el-form :model="form" :rules="rules" ref="formRef" label-width="auto" :validate-on-rule-change="false">
      <el-row :gutter="12">
{$formItemsCode}
      </el-row>
    </el-form>
    <template #footer>
      <span class="dialog-footer">
        <el-button @click="dialogVisible=false">{{ t('common.actions.cancel') }}</el-button>
        <el-button type="primary" @click="confirmSubmit">{{ t('common.actions.confirm') }}</el-button>
      </span>
    </template>
  </el-dialog>
</template>

<style scoped>
.box {
  display: flex;
  flex-direction: column;
  height: 100%
}

.table {
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto
}
</style>
VUE;
    }

    // ==================== 辅助方法 ====================

    private function getListFields(): array
    {
        return array_filter($this->config['columns'], fn($c) => !empty($c['show_in_list']));
    }

    private function getSearchFields(): array
    {
        return array_filter($this->config['columns'], fn($c) => !empty($c['show_in_search']));
    }

    private function getFormFields(): array
    {
        return array_filter($this->config['columns'], fn($c) => !empty($c['show_in_form']));
    }

    private function buildSearchConditions(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            $lines[] = "        if (!empty(\$param['{$name}'])) {";
            if ($f['form_type'] === 'input' || $f['form_type'] === 'textarea') {
                $lines[] = "            \$query->where('{$name}', 'like', \$param['{$name}'] . '%');";
            } else {
                $lines[] = "            \$query->where('{$name}', \$param['{$name}']);";
            }
            $lines[] = "        }";
        }
        return implode("\n", $lines);
    }

    private function buildDepSearchWhen(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            if ($f['form_type'] === 'input' || $f['form_type'] === 'textarea') {
                $lines[] = "            ->when(!empty(\$param['{$name}']), fn(\$q) => \$q->where('{$name}', 'like', \$param['{$name}'] . '%'))";
            } else {
                $lines[] = "            ->when(!empty(\$param['{$name}']), fn(\$q) => \$q->where('{$name}', \$param['{$name}']))";
            }
        }
        return implode("\n", $lines);
    }

    private function buildListMapping(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            // 时间字段特殊处理
            if (str_contains($name, '_at') || str_contains($name, 'time')) {
                $lines[] = "                '{$name}' => \$item['{$name}']->toDateTimeString(),";
            } else {
                $lines[] = "                '{$name}' => \$item['{$name}'],";
            }
        }
        return implode("\n", $lines);
    }

    private function buildValidateRules(array $fields, string $scene): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            $rules = [];
            
            if ($f['is_nullable'] === 'NO' && $scene === 'add') {
                $rules[] = 'required';
            }
            
            if (in_array($f['data_type'], ['int', 'bigint', 'tinyint'])) {
                $rules[] = 'integer';
            } elseif (!empty($f['max_length'])) {
                $rules[] = "max:{$f['max_length']}";
            }

            if (!empty($rules)) {
                $lines[] = "            '{$name}' => '" . implode('|', $rules) . "',";
            }
        }
        return implode("\n", $lines);
    }

    private function buildVueSearchFields(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            $label = $f['column_comment'] ?: $name;
            $type = $f['form_type'] === 'select' ? 'select-v2' : 'input';
            $lines[] = "  {key: '{$name}', type: '{$type}', label: '{$label}', placeholder: '{$label}', width: 200},";
        }
        return implode("\n", $lines);
    }

    private function buildVueColumns(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            $label = $f['column_comment'] ?: $name;
            $lines[] = "  {key: '{$name}', label: '{$label}'},";
        }
        return implode("\n", $lines);
    }

    private function buildVueFormItems(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            $label = $f['column_comment'] ?: $name;
            $required = $f['is_nullable'] === 'NO' ? ' required' : '';
            
            $lines[] = "        <el-col :md=\"12\" :span=\"24\">";
            $lines[] = "          <el-form-item label=\"{$label}\" prop=\"{$name}\"{$required}>";
            
            switch ($f['form_type']) {
                case 'textarea':
                    $lines[] = "            <el-input v-model=\"form.{$name}\" type=\"textarea\" :rows=\"3\" clearable/>";
                    break;
                case 'number':
                    $lines[] = "            <el-input-number v-model=\"form.{$name}\" :min=\"0\"/>";
                    break;
                case 'select':
                    $lines[] = "            <el-select-v2 v-model=\"form.{$name}\" :options=\"[]\" style=\"width:100%\"/>";
                    $lines[] = "            <!-- TODO: 配置选项 -->";
                    break;
                case 'date':
                    $lines[] = "            <el-date-picker v-model=\"form.{$name}\" type=\"date\" style=\"width:100%\"/>";
                    break;
                case 'datetime':
                    $lines[] = "            <el-date-picker v-model=\"form.{$name}\" type=\"datetime\" style=\"width:100%\"/>";
                    break;
                default:
                    $lines[] = "            <el-input v-model=\"form.{$name}\" clearable/>";
            }
            
            $lines[] = "          </el-form-item>";
            $lines[] = "        </el-col>";
        }
        return implode("\n", $lines);
    }

    private function buildVueFormData(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            if (in_array($f['data_type'], ['int', 'bigint', 'tinyint'])) {
                $default = '0';
            } elseif ($f['form_type'] === 'select') {
                $default = "''";
            } else {
                $default = "''";
            }
            $lines[] = "  {$name}: {$default},";
        }
        return implode("\n", $lines);
    }

    private function buildVueFormRules(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            if ($f['is_nullable'] === 'NO') {
                $name = $f['column_name'];
                $label = $f['column_comment'] ?: $name;
                $lines[] = "  {$name}: [{required: true, message: '{$label}' + t('common.required'), trigger: 'blur'}],";
            }
        }
        return implode("\n", $lines);
    }

    private function guessModelClass(): array
    {
        // 根据表名猜测 Model 类
        $parts = explode('_', $this->tableName);
        $className = implode('', array_map('ucfirst', $parts)) . 'Model';
        
        // 检查常见位置
        $possiblePaths = [
            "app\\model\\{$this->domain}\\{$className}",
            "app\\model\\{$className}",
        ];

        foreach ($possiblePaths as $path) {
            if (class_exists($path)) {
                return [
                    'import' => "use {$path};",
                    'name' => $className,
                ];
            }
        }

        // 默认返回路径（即使不存在，生成后可以手动创建）
        return [
            'import' => "use app\\model\\{$this->domain}\\{$className};",
            'name' => $className,
        ];
    }
}
