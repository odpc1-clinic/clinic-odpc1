<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class CardReaderController extends Controller
{
    public function readCard()
    {
        $jsonPath = public_path('EFormAgent/carddata.json');

        if (!File::exists($jsonPath)) {
            return response()->json(['error' => 'ไม่พบไฟล์ข้อมูลบัตรประชาชน'], 404);
        }

        $json = File::get($jsonPath);
        $data = json_decode($json, true);

        if (!$data || !isset($data['pid'])) {
            return response()->json(['error' => 'ข้อมูลไม่ถูกต้องหรือไม่สมบูรณ์'], 400);
        }

        return response()->json($data);
    }
}
