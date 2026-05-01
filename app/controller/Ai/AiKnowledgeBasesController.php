<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiKnowledgeBasesModule;
use support\Request;

class AiKnowledgeBasesController extends Controller
{
    public function init(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'detail'], $request); }

    /** @OperationLog("知识库新增") @Permission("ai_knowledge_add") */
    public function add(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'add'], $request); }

    /** @OperationLog("知识库编辑") @Permission("ai_knowledge_edit") */
    public function edit(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'edit'], $request); }

    /** @OperationLog("知识库删除") @Permission("ai_knowledge_del") */
    public function del(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'del'], $request); }

    /** @OperationLog("知识库状态切换") @Permission("ai_knowledge_status") */
    public function status(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'status'], $request); }

    public function documents(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'documents'], $request); }
    public function documentDetail(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'documentDetail'], $request); }

    /** @OperationLog("知识库文档新增") @Permission("ai_knowledge_document_add") */
    public function addDocument(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'addDocument'], $request); }

    /** @OperationLog("知识库文档编辑") @Permission("ai_knowledge_document_edit") */
    public function editDocument(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'editDocument'], $request); }

    /** @OperationLog("知识库文档删除") @Permission("ai_knowledge_document_del") */
    public function delDocument(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'delDocument'], $request); }

    /** @OperationLog("知识库文档重建索引") @Permission("ai_knowledge_reindex") */
    public function reindexDocument(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'reindexDocument'], $request); }

    public function chunks(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'chunks'], $request); }

    /** @OperationLog("知识库召回测试") @Permission("ai_knowledge_retrieval_test") */
    public function retrievalTest(Request $request) { return $this->run([AiKnowledgeBasesModule::class, 'retrievalTest'], $request); }
}
