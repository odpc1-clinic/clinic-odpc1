<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\LabSub;
use App\Support\GcAst;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ลงผลความไวต่อยาของเชื้อหนองใน (GC AST) ต่อจากผล Culture = Growth / GC Growth (ห้อง Lab ขอ 2569-09-30)
 * แยกหน้าออกมา ไม่ยัดเข้าตารางลงผลเดิม เพราะตารางนั้นอยู่ในฟอร์มของหน้าลงผล และบางหน้าโหลดผ่าน AJAX
 */
class LabAstController extends Controller
{
    public function edit($id)
    {
        $sub = LabSub::findOrFail($id);
        $lab = Lab::findOrFail($sub->lab_id);
        $patient = $lab->patient;
        // พยาบาล/หมอเปิดดูได้ (ลิงก์ "ดูผล AST เต็ม") แต่แก้ไม่ได้ — หน้าจอเป็นแบบอ่านอย่างเดียว
        $canEdit = $this->canEdit();
        // นอกห้อง Lab เห็นได้เฉพาะผลที่ approve แล้ว — กติกาเดียวกับผล Lab อื่น (เปิด URL ตรงก็ไม่เห็น)
        if (! $canEdit && (! $lab->isResultVisible() || GcAst::pendingApproval($lab, $sub->ast))) {
            abort(403, 'ผล AST นี้ยังไม่ได้ Approve');
        }

        return view('lab-sub.ast', compact('sub', 'lab', 'patient', 'canEdit'));
    }

    /** ลงผล/แก้ผล AST ได้เฉพาะห้อง Lab (Admin ไม่ใช่ superuser แต่เป็นผู้ดูแลห้อง Lab ด้วย) */
    private function canEdit(): bool
    {
        return Auth::check() && Auth::user()->hasAnyRole(['Lab', 'Admin']);
    }

    /** ใบรายงาน AST ตามหัวฟอร์ม Laboratory Report ของ สคร.1 */
    public function print($id)
    {
        $sub = LabSub::findOrFail($id);
        $lab = Lab::findOrFail($sub->lab_id);
        $patient = $lab->patient;
        $visit = \App\Models\Visit::find($lab->visit_id);

        return view('lab-sub.ast_print', compact('sub', 'lab', 'patient', 'visit'));
    }

    public function save(Request $request, $id)
    {
        abort_unless($this->canEdit(), 403, 'ลงผล AST ได้เฉพาะห้อง Lab');
        $sub = LabSub::findOrFail($id);
        $lab = Lab::findOrFail($sub->lab_id);
        $in  = (array) $request->input('drugs', []);

        $drugs = [];
        foreach (GcAst::DRUGS as $k => $def) {
            $v = mb_substr(trim((string) ($in[$k]['v'] ?? '')), 0, 30);
            $i = (string) ($in[$k]['i'] ?? '');
            // ไม่ได้เลือกผลแปลเอง = ใช้ที่ระบบเสนอจากค่าที่กรอก
            if (! array_key_exists($i, GcAst::INTERP)) { $i = (string) GcAst::interpret($k, $v); }
            if ($v === '' && $i === '') { continue; }
            $drugs[$k] = ['v' => $v, 'i' => $i];
        }

        $sub->ast = $drugs ? [
            'drugs'   => $drugs,
            'comment' => mb_substr(trim((string) $request->input('comment', '')), 0, 500) ?: null,
            'method'  => mb_substr(trim((string) $request->input('method', '')), 0, 1000) ?: null,
            'by'      => Auth::id(),
            // เก็บถึงวินาที — ใช้เทียบกับเวลา Approve ว่าผล AST นี้ approve แล้วหรือยัง
            'at'      => now()->format('Y-m-d H:i:s'),
        ] : null;
        // กดบันทึกซ้ำโดยไม่ได้แก้อะไร ต้องไม่ขยับเวลา — ไม่งั้นผลที่ approve แล้วจะกลายเป็น "รอ approve" เปล่า ๆ
        $core = function ($a) { return $a ? array_intersect_key((array) $a, array_flip(['drugs', 'comment', 'method'])) : null; };
        if ($core($sub->ast) == $core($sub->getOriginal('ast'))) {
            flash('ไม่มีอะไรเปลี่ยน', 'info');
            return redirect()->route('lab-sub.ast', $sub->id);
        }
        $sub->save();

        $alerts = GcAst::alerts($sub->ast);
        $reApprove = $sub->ast && GcAst::pendingApproval($lab, $sub->ast)
            ? ' · ใบนี้ Approve ไปแล้ว ต้องกด Approve อีกครั้ง พยาบาล/หมอถึงจะเห็นผล AST นี้' : '';
        flash(($alerts
            ? '⚠ บันทึกผล AST แล้ว — พบเชื้อดื้อ ' . implode(', ', $alerts) . ' ต้องแจ้งแพทย์/ผู้รับผิดชอบ'
            : 'บันทึกผล AST แล้ว') . $reApprove, $alerts || $reApprove ? 'danger' : 'success');

        return redirect()->route('lab-sub.ast', $sub->id);
    }
}
