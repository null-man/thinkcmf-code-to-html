<?php
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\facade\Db;

abstract class BaseGenerateController extends AdminBaseController
{
    // 子类需要实现的属性
    protected $model;           // 模型名称
    protected $showFields;      // 显示字段
    protected $searchFields;    // 搜索字段
    protected $orderBy;         // 排序
    protected $pageSize = 10;   // 每页数量
    protected $formFields;      // 表单字段
    protected $formRules = [];  // 表单验证规则
    protected $fieldLabels = [];    // 字段显示名称
    
    // 添加自定义字段类型映射
    protected $fieldTypes = [];  // 自定义字段类型映射 //editor switch

    protected $listEditAbleField = []; // list页面字段可以编辑字段
    protected $config = []; //list 操作模块 有edit和delete
    
    protected function initialize()
    {
        parent::initialize();
        $this->config = ['delete','edit'];
    }
    /**
     * 列表页
     */
    public function index()
    {
        $query = $this->buildQuery();
        $list = $query->paginate($this->pageSize);
        
        // 获取字段注释
        $tableName = $this->model->getTable();
        $columns = Db::query("SHOW FULL COLUMNS FROM {$tableName}");
        $comments = [];
        foreach ($columns as $column) {
            $comments[$column['Field']] = $this->fieldLabels[$column['Field']] 
                ?? $column['Comment'] 
                ?? $column['Field'];
            $types[$column['Field']] = $this->parseFieldType($column['Type'], $column['Field']);
        }
        
        $this->assign([
            'list' => $list,
            'page' => $list->render(),
            'showFields' => $this->showFields,
            'searchFields' => $this->searchFields,
            'comments' => $comments,
            'types' => $types,
            'config' => $this->config,
            'listEditAbleField' => $this->listEditAbleField
        ]);
        
        return $this->fetch(':commontpl/index');
    }
    
    /**
     * 构建查询
     */
    protected function buildQuery()
    {
        $query = $this->model::order($this->orderBy);
        
        // 处理搜索
        $param = $this->request->param();
        if (!empty($this->searchFields)) {
            foreach ($this->searchFields as $field) {
                if (!empty($param[$field])) {
                    $query->where($field, 'like', "%{$param[$field]}%");
                }
            }
        }
        
        return $query;
    }
    
    /**
     * 添加页面
     */
    public function add()
    {
        if ($this->request->isPost()) {
            return $this->doAdd();
        }
        
        // 获取字段注释
        $tableName = $this->model->getTable();
        $columns = Db::query("SHOW FULL COLUMNS FROM {$tableName}");
        $comments = [];
        $types = [];
        foreach ($columns as $column) {
            $comments[$column['Field']] = $this->fieldLabels[$column['Field']] 
                ?? $column['Comment'] 
                ?? $column['Field'];
            $types[$column['Field']] = $this->parseFieldType($column['Type'], $column['Field']);
        }
        
        // 如果没有设置表单字段，默认使用显示字段
        if (empty($this->formFields)) {
            $this->formFields = $this->showFields;
        }
        
        $this->assign([
            'formFields' => $this->formFields,
            'comments' => $comments,
            'types' => $types
        ]);
        
        return $this->fetch(':commontpl/add');
    }
    
    /**
     * 处理添加
     */
    public function doAdd()
    {
        $data = $this->request->post();
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $this->model->save($data);
        $this->success("添加成功！", url('index'));
    }

    /**
     * 编辑页面 
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            return $this->doEdit();
        }

        $id = $this->request->param('id', 0, 'intval');
        $data = $this->model->find($id);

        // 获取字段注释
        $tableName = $this->model->getTable();
        $columns = Db::query("SHOW FULL COLUMNS FROM {$tableName}");
        $comments = [];
        $types = [];
        foreach ($columns as $column) {
            $comments[$column['Field']] = $this->fieldLabels[$column['Field']] 
                ?? $column['Comment'] 
                ?? $column['Field'];
            $types[$column['Field']] = $this->parseFieldType($column['Type'], $column['Field']);
        }
        
        // 如果没有设置表单字段，默认使用显示字段
        if (empty($this->formFields)) {
            $this->formFields = $this->showFields;
        }
        
        $this->assign([
            'data' => $data,
            'formFields' => $this->formFields,
            'comments' => $comments,
            'types' => $types
        ]);
        
        return $this->fetch(':commontpl/edit');
    }

    /**
     * 处理编辑
     */
    public function doEdit()
    {
        $data = $this->request->post();
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->model->where('id', $data['id'])->update($data);
        $this->success('编辑成功！');
    }

    /**
     * 开关操作
     */
    public function switch()
    {
        $id = $this->request->param('id', 0, 'intval');
        $field = $this->request->param('field', '');
        $value = $this->request->param('value', 0, 'intval');

        // 验证字段是否允许切换
        if (!isset($this->fieldTypes[$field]) || $this->fieldTypes[$field] !== 'switch') {
            $this->error('非法操作！');
        }

        // 更新数据
        $result = $this->model
            ->where('id', $id)
            ->update([$field => $value]);

        if ($result !== false) {
            $this->success('操作成功！');
        } else {
            $this->error('操作失败！');
        }
    }

    /**删除 */
    public function delete()
    {
        $id = $this->request->param('id', 0, 'intval');
        $this->model->where('id', $id)->delete();
        $this->success('删除成功！');
    }

    public function batchDelete()
    {
        $ids = $this->request->param('ids/a');
        if (empty($ids)) {
            $this->error('请选择要删除的记录！');
        }
        
        if ($this->model->where('id', 'in', $ids)->delete()) {
            $this->success('删除成功！');
        } else {
            $this->error('删除失败！');
        }
    }

    public function listEdit() {
        $id = $this->request->param('id');
        $field = $this->request->param('field');
        $value = $this->request->param('value');

        $this->model->where('id',$id)->update([$field=>$value]);
        $this->success('更新成功！');
    }

    /**
     * 批量编辑
     */
    public function batchEdit()
    {
        if (!$this->request->isPost()) {
            $this->error('非法请求！');
        }
        
        $field = $this->request->param('field');
        $value = $this->request->param('value');
        $ids = $this->request->param('ids');
        
        // 验证字段是否在允许编辑的字段列表中
        if (!in_array($field, $this->showFields)) {
            $this->error('该字段不允许编辑！');
        }
        
        // 禁止编辑的字段
        $forbiddenFields = ['id', 'created_at', 'updated_at'];
        if (in_array($field, $forbiddenFields)) {
            $this->error('该字段不允许编辑！');
        }
        
        // 转换ids为数组
        $ids = explode(',', $ids);
        if (empty($ids)) {
            $this->error('请选择要编辑的记录！');
        }
        
        // 更新数据
        $data = [
            $field => $value
        ];
        
        // 如果存在updated_at字段，自动更新
        if (in_array('updated_at', $this->showFields)) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $result = $this->model->whereIn('id', $ids)->update($data);
        
        if ($result !== false) {
            $this->success('更新成功！');
        } else {
            $this->error('更新失败！');
        }
    }
    
    /**
     * 解析字段类型
     */
    protected function parseFieldType($type, $field = '')
    {
        // 优先检查字段名称的映射
        if (!empty($field) && isset($this->fieldTypes[$field])) {
            return $this->fieldTypes[$field];
        }
        
        // 默认类型判断
        if (strpos($type, 'int') !== false) {
            return 'number';
        } elseif (strpos($type, 'text') !== false) {
            return 'textarea';
        } elseif (strpos($type, 'datetime') !== false) {
            return 'datetime';
        } elseif (strpos($type, 'date') !== false) {
            return 'date';
        } else {
            return 'text';
        }
    }
} 