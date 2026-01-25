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
            'model' => [
                'path' => "app/model/{$this->domain}/{$this->moduleName}Model.php",
                'content' => $this->generateModel(),
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

    // ==================== 生成方法 ====================

    /**
     * 生成 Model
     */
    private function generateModel(): string
    {
        $code = <<<'PHP'
<?php

namespace app\model\{DOMAIN};

use support\Model;

class {MODULE}Model extends Model
{
    public $table = '{TABLE_NAME}';
}
PHP;

        return str_replace(
            ['{DOMAIN}', '{MODULE}', '{TABLE_NAME}'],
            [$this->domain, $this->moduleName, $this->tableName],
            $code
        );
    }

    /**
     * 生成 Controller
     */
    private function generateController(): string
    {
        $code = <<<'PHP'
<?php

namespace app\controller\{DOMAIN};

use app\controller\Controller;
use app\module\{DOMAIN}\{MODULE}Module;
use support\Request;

class {MODULE}Controller extends Controller
{
    public function init(Request $request) { return $this->run([{MODULE}Module::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([{MODULE}Module::class, 'list'], $request); }

    /** @OperationLog("{MENU_NAME}新增") @Permission("{DOMAIN_LOWER}_{MODULE_LOWER}_add") */
    public function add(Request $request) { return $this->run([{MODULE}Module::class, 'add'], $request); }

    /** @OperationLog("{MENU_NAME}编辑") @Permission("{DOMAIN_LOWER}_{MODULE_LOWER}_edit") */
    public function edit(Request $request) { return $this->run([{MODULE}Module::class, 'edit'], $request); }

    /** @OperationLog("{MENU_NAME}删除") @Permission("{DOMAIN_LOWER}_{MODULE_LOWER}_del") */
    public function del(Request $request) { return $this->run([{MODULE}Module::class, 'del'], $request); }
}
PHP;

        return str_replace(
            ['{DOMAIN}', '{DOMAIN_LOWER}', '{MODULE}', '{MODULE_LOWER}', '{MENU_NAME}'],
            [$this->domain, $this->domainLower, $this->moduleName, $this->moduleNameLower, $this->config['menu_name']],
            $code
        );
    }

    /**
     * 生成 Module
     */
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

        $code = <<<'PHP'
<?php

namespace app\module\{DOMAIN};

use app\dep\{DOMAIN}\{MODULE}Dep;
use app\module\BaseModule;
use app\validate\{DOMAIN}\{MODULE}Validate;

class {MODULE}Module extends BaseModule
{
    protected {MODULE}Dep ${MODULE_LOWER}Dep;

    public function __construct()
    {
        $this->{MODULE_LOWER}Dep = new {MODULE}Dep();
    }

    public function init($request): array
    {
        return self::success([
            'dict' => []
        ]);
    }

    public function list($request): array
    {
        $param = $request->all();
        
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;
        
        $res = $this->{MODULE_LOWER}Dep->list($param);
        
        $list = $res->map(function ($item) {
            return [
{LIST_MAPPING}
            ];
        });
        
        $page = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        
        return self::paginate($list, $page);
    }

    public function add($request): array
    {
        $param = $this->validate($request, {MODULE}Validate::add());
        
        $this->{MODULE_LOWER}Dep->add($param);
        
        return self::success();
    }

    public function edit($request): array
    {
        $param = $this->validate($request, {MODULE}Validate::edit());
        
        $row = $this->{MODULE_LOWER}Dep->get((int)$param['id']);
        self::throwNotFound($row);
        
        $this->{MODULE_LOWER}Dep->update((int)$param['id'], $param);
        
        return self::success();
    }

    public function del($request): array
    {
        $param = $this->validate($request, {MODULE}Validate::del());
        
        $this->{MODULE_LOWER}Dep->delete($param['id']);
        
        return self::success();
    }
}
PHP;

        return str_replace(
            ['{DOMAIN}', '{MODULE}', '{MODULE_LOWER}', '{LIST_MAPPING}'],
            [$this->domain, $this->moduleName, $this->moduleNameLower, $listMapping],
            $code
        );
    }

    /**
     * 生成 Dep
     */
    private function generateDep(): string
    {
        $searchFields = $this->getSearchFields();
        $searchWhen = $this->buildDepSearchWhen($searchFields);
        $modelClass = $this->guessModelClass();

        $code = <<<'PHP'
<?php

namespace app\dep\{DOMAIN};

use app\dep\BaseDep;
{MODEL_IMPORT}
use app\enum\CommonEnum;
use support\Model;

class {MODULE}Dep extends BaseDep
{
    protected function createModel(): Model
    {
        return new {MODEL_NAME}();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
{SEARCH_WHEN}
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }
}
PHP;

        return str_replace(
            ['{DOMAIN}', '{MODULE}', '{MODEL_IMPORT}', '{MODEL_NAME}', '{SEARCH_WHEN}'],
            [$this->domain, $this->moduleName, $modelClass['import'], $modelClass['name'], $searchWhen],
            $code
        );
    }

    /**
     * 生成 Validate
     */
    private function generateValidate(): string
    {
        $formFields = $this->getFormFields();
        $addRules = $this->buildRespectValidateRules($formFields, 'add');
        $editRules = $this->buildRespectValidateRules($formFields, 'edit');

        $code = <<<'PHP'
<?php

namespace app\validate\{DOMAIN};

use Respect\Validation\Validator as v;

class {MODULE}Validate
{
    public static function add(): array
    {
        return [
{ADD_RULES}
        ];
    }

    public static function edit(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
{EDIT_RULES}
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()->positive()),
            'current_page' => v::optional(v::intVal()->positive()),
        ];
    }
}
PHP;

        return str_replace(
            ['{DOMAIN}', '{MODULE}', '{ADD_RULES}', '{EDIT_RULES}'],
            [$this->domain, $this->moduleName, $addRules, $editRules],
            $code
        );
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
        $cellTemplates = $this->buildVueCellTemplates($listFields);
        $formItemsCode = $this->buildVueFormItems($formFields);
        $formDataCode = $this->buildVueFormData($formFields);
        $formRulesCode = $this->buildVueFormRules($formFields);
        
        // 检测需要的组件导入
        $needsEditor = false;
        $needsUpMedia = false;
        foreach ($formFields as $f) {
            if ($f['form_type'] === 'editor') $needsEditor = true;
            if ($f['form_type'] === 'image') $needsUpMedia = true;
        }
        
        $extraImports = '';
        if ($needsEditor) {
            $extraImports .= "\nimport {Editor} from '@/components/Editor'";
        }
        if ($needsUpMedia) {
            $extraImports .= "\nimport { UpMedia } from '@/components/UpMedia'";
        }

        return <<<VUE
<script setup lang="ts">
import {ref, computed, onMounted, nextTick} from 'vue'
import {useI18n} from 'vue-i18n'
import {{$this->moduleName}Api} from '@/api/{$this->domainLower}/{$this->moduleNameLower}'
import {useIsMobile} from '@/hooks/useResponsive'
import {ElNotification, ElIcon} from 'element-plus'
import {ArrowRight} from '@element-plus/icons-vue'
import type {FormInstance, FormRules} from 'element-plus'
import {AppTable} from '@/components/Table'
import {Search} from '@/components/Search'
import type {SearchField} from '@/components/Search/types'
import {useUserStore} from '@/store/user'
import {useTable} from '@/hooks/useTable'{$extraImports}

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
              <ElIcon class="el-icon--right">
                <ArrowRight/>
              </ElIcon>
            </el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item @click="batchDel" v-if="userStore.can('{$this->moduleNameLower}.del')">{{ t('common.actions.batchDelete') }}</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </template>
{$cellTemplates}
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
            $type = $f['data_type'];
            
            // 字符串类型使用 like 查询（需要 trim 去除空格）
            if ($f['form_type'] === 'input' || $f['form_type'] === 'textarea') {
                $lines[] = "            ->when(!empty(trim(\$param['{$name}'] ?? '')), fn(\$q) => \$q->where('{$name}', 'like', trim(\$param['{$name}']) . '%'))";
            } else {
                // 数字类型需要检查 isset 而不是 empty
                if (in_array($type, ['int', 'bigint', 'tinyint', 'smallint'])) {
                    $lines[] = "            ->when(isset(\$param['{$name}']) && \$param['{$name}'] !== '', fn(\$q) => \$q->where('{$name}', \$param['{$name}']))";
                } else {
                    $lines[] = "            ->when(!empty(\$param['{$name}']), fn(\$q) => \$q->where('{$name}', \$param['{$name}']))";
                }
            }
        }
        return implode("\n", $lines);
    }

    private function buildListMapping(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            // 直接返回原始值，不做类型转换
            // 数据库返回的日期时间字符串可以直接给前端使用
            $lines[] = "                '{$name}' => \$item->{$name},";
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

    /**
     * 构建 Respect\Validation 验证规则
     */
    private function buildRespectValidateRules(array $fields, string $scene): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            $label = $f['column_comment'] ?: $name;
            $type = $f['data_type'];
            $isRequired = $f['is_nullable'] === 'NO';
            
            $rule = '';
            
            // 特殊字段验证
            if (str_contains($name, 'email')) {
                if ($scene === 'add' && $isRequired) {
                    $rule = "v::email()";
                } else {
                    $rule = "v::optional(v::email())";
                }
            } elseif (str_contains($name, 'phone') || str_contains($name, 'mobile')) {
                // 使用正则验证手机号（支持中国手机号格式）
                if ($scene === 'add' && $isRequired) {
                    $rule = "v::regex('/^1[3-9]\\d{9}$/')";
                } else {
                    $rule = "v::optional(v::regex('/^1[3-9]\\d{9}$/'))";
                }
            } elseif (str_contains($name, 'url') || str_contains($name, 'link')) {
                if ($scene === 'add' && $isRequired) {
                    $rule = "v::url()";
                } else {
                    $rule = "v::optional(v::url())";
                }
            }
            // 根据数据类型生成规则
            elseif (in_array($type, ['int', 'bigint', 'tinyint', 'smallint'])) {
                // sex 字段可以为 0，使用 min(0) 而不是 positive()
                if ($name === 'sex' || str_contains($name, 'gender')) {
                    if ($scene === 'add' && $isRequired) {
                        $rule = "v::intVal()->min(0)";
                    } else {
                        $rule = "v::optional(v::intVal()->min(0))";
                    }
                } else {
                    if ($scene === 'add' && $isRequired) {
                        $rule = "v::intVal()->positive()";
                    } else {
                        $rule = "v::optional(v::intVal()->positive())";
                    }
                }
            } elseif (in_array($type, ['decimal', 'float', 'double'])) {
                if ($scene === 'add' && $isRequired) {
                    $rule = "v::floatVal()";
                } else {
                    $rule = "v::optional(v::floatVal())";
                }
            } elseif (in_array($type, ['varchar', 'char'])) {
                $maxLen = $f['max_length'] ?? 255;
                if ($scene === 'add' && $isRequired) {
                    $rule = "v::stringType()->length(1, {$maxLen})";
                } else {
                    $rule = "v::optional(v::stringType()->length(0, {$maxLen}))";
                }
            } elseif (in_array($type, ['text', 'longtext', 'mediumtext'])) {
                if ($scene === 'add' && $isRequired) {
                    $rule = "v::stringType()";
                } else {
                    $rule = "v::optional(v::stringType())";
                }
            } elseif (in_array($type, ['date', 'datetime', 'timestamp'])) {
                // date 类型用 date('Y-m-d')，datetime/timestamp 用 stringType()（因为包含时间）
                if ($type === 'date') {
                    $rule = "v::optional(v::date('Y-m-d'))";
                } else {
                    $rule = "v::optional(v::stringType())";
                }
            } else {
                // 默认字符串
                if ($scene === 'add' && $isRequired) {
                    $rule = "v::stringType()";
                } else {
                    $rule = "v::optional(v::stringType())";
                }
            }
            
            $lines[] = "            '{$name}' => {$rule}->setName('{$label}'),";
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
            
            // 长文本字段添加 overflowTooltip
            if (in_array($f['form_type'], ['textarea', 'editor'])) {
                $lines[] = "  {key: '{$name}', label: '{$label}', overflowTooltip: true},";
            } else {
                $lines[] = "  {key: '{$name}', label: '{$label}'},";
            }
        }
        return implode("\n", $lines);
    }
    
    /**
     * 构建自定义列模板
     */
    private function buildVueCellTemplates(array $fields): string
    {
        $templates = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            
            // 图片字段（无预览功能）
            if ($f['form_type'] === 'image') {
                $templates[] = <<<TEMPLATE
        <template #cell-{$name}="{ row }">
          <el-image v-if="row.{$name}" :src="row.{$name}" style="width: 50px; height: 50px" fit="cover" />
        </template>
TEMPLATE;
            }
            
            // 状态字段
            if ($name === 'status') {
                $templates[] = <<<TEMPLATE
        <template #cell-status="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status_name || row.status }}</el-tag>
        </template>
TEMPLATE;
            }
        }
        
        return implode("\n", $templates);
    }

    private function buildVueFormItems(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $name = $f['column_name'];
            $label = $f['column_comment'] ?: $name;
            $required = $f['is_nullable'] === 'NO' ? ' required' : '';
            
            // 判断是否需要独占一行（移动端兼容）
            $isFullWidth = in_array($f['form_type'], ['textarea', 'editor']);
            $colSpan = $isFullWidth ? ':span="24"' : ':md="12" :span="24"';
            
            $lines[] = "        <el-col {$colSpan}>";
            $lines[] = "          <el-form-item label=\"{$label}\" prop=\"{$name}\"{$required}>";
            
            switch ($f['form_type']) {
                case 'password':
                    $lines[] = "            <el-input v-model=\"form.{$name}\" type=\"password\" show-password clearable/>";
                    break;
                case 'textarea':
                    $lines[] = "            <el-input v-model=\"form.{$name}\" type=\"textarea\" :rows=\"3\" clearable/>";
                    break;
                case 'editor':
                    $lines[] = "            <Editor v-model=\"form.{$name}\" />";
                    break;
                case 'number':
                    $lines[] = "            <el-input-number v-model=\"form.{$name}\" :min=\"0\" style=\"width:100%\"/>";
                    break;
                case 'select':
                    $lines[] = "            <el-select-v2 v-model=\"form.{$name}\" :options=\"dict.{$name}_arr || []\" style=\"width:100%\"/>";
                    break;
                case 'date':
                    $lines[] = "            <el-date-picker v-model=\"form.{$name}\" type=\"date\" value-format=\"YYYY-MM-DD\" style=\"width:100%\"/>";
                    break;
                case 'datetime':
                    $lines[] = "            <el-date-picker v-model=\"form.{$name}\" type=\"datetime\" value-format=\"YYYY-MM-DD HH:mm:ss\" style=\"width:100%\"/>";
                    break;
                case 'image':
                    $lines[] = "            <UpMedia v-model=\"form.{$name}\" folder-name=\"{$name}s\" width=\"80px\" show-input/>";
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
            
            // 根据字段类型和名称设置合理的默认值
            if ($f['form_type'] === 'select') {
                // select 类型：status/is_ 默认为 1，sex 默认为 0，其他默认为空或 1
                if (str_contains($name, 'status') || str_contains($name, 'is_')) {
                    $default = '1';
                } elseif ($name === 'sex' || str_contains($name, 'gender')) {
                    $default = '0';
                } elseif ($name === 'type' || str_contains($name, 'level') || str_contains($name, 'category')) {
                    $default = '1';  // type 等分类字段默认选第一个选项
                } else {
                    $default = "''";
                }
            } elseif (in_array($f['data_type'], ['int', 'bigint', 'tinyint'])) {
                // 数字类型，如果是 status 或 is_ 开头默认为 1，sex 默认为 0，否则为 0
                if (str_contains($name, 'status') || str_contains($name, 'is_')) {
                    $default = '1';
                } elseif ($name === 'sex' || str_contains($name, 'gender')) {
                    $default = '0';
                } else {
                    $default = '0';
                }
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
        
        // 直接返回标准路径（因为 Model 会生成在这个位置）
        // 不使用 class_exists() 检查，因为在生成阶段 Model 文件还不存在
        return [
            'import' => "use app\\model\\{$this->domain}\\{$className};",
            'name' => $className,
        ];
    }
}
