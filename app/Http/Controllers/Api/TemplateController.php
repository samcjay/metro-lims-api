<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormulaNode;
use App\Models\WorksheetTemplate;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index()
    {
        $templates = WorksheetTemplate::withCount('formulaNodes')->get();
        return response()->json($templates);
    }

    public function show($id)
    {
        $template = WorksheetTemplate::with(['formulaNodes', 'cmcEntries', 'fields'])->findOrFail($id);
        return response()->json($template);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'                => 'required|unique:worksheet_templates',
            'name'                => 'required|string',
            'instrument_type'     => 'required|string',
            'test_method_ref'     => 'nullable|string',
            'unit'                => 'required|string',
            'max_test_points'     => 'required|integer|min:1',
            'cycles'              => 'required|integer|min:1',
            'has_before_after'    => 'boolean',
            'has_head_correction' => 'boolean',
        ]);

        $data['created_by'] = 1; // TODO: replace with auth()->id() once auth is added
        $template = WorksheetTemplate::create($data);
        return response()->json($template, 201);
    }

    public function update(Request $request, $id)
    {
        $template = WorksheetTemplate::findOrFail($id);
        $template->update($request->all());
        return response()->json($template);
    }

    public function nodes($id)
    {
        $template = WorksheetTemplate::with('formulaNodes')->findOrFail($id);
        return response()->json([
            'template' => $template->only('id', 'name', 'code'),
            'nodes'    => $template->formulaNodes,
        ]);
    }

    public function storeNode(Request $request, $id)
    {
        $template = WorksheetTemplate::findOrFail($id);
        $node = $template->formulaNodes()->create($request->validate([
            'key'              => 'required|string',
            'label'            => 'required|string',
            'symbol'           => 'nullable|string',
            'uncertainty_type' => 'required|in:A,B',
            'formula'          => 'required|string',
            'divisor_formula'  => 'required|string',
            'sort_order'       => 'integer',
        ]));
        return response()->json($node, 201);
    }

    public function updateNode(Request $request, $templateId, $nodeId)
    {
        $node = FormulaNode::findOrFail($nodeId);
        $node->update($request->all());
        return response()->json($node);
    }

    public function destroyNode($templateId, $nodeId)
    {
        FormulaNode::findOrFail($nodeId)->delete();
        return response()->json(['message' => 'Node deleted.']);
    }
}
