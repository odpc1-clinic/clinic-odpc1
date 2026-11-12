<?php

/**
 * ประกาศความเป็นส่วนตัว (Privacy Notice) ของศูนย์บริการเวชศาสตร์ป้องกัน สคร.1 เชียงใหม่ — ร่าง 2569-10-29
 * ใช้ทั้งหน้าเว็บ /privacy และไฟล์ Word ที่ส่งให้ DPO กรมตรวจ (แก้ที่นี่ที่เดียว)
 * โครงตามแบบฟอร์มกรมควบคุมโรค "1. คำประกาศเกี่ยวกับความเป็นส่วนตัว (Privacy Notice).docx"
 * แก้เนื้อหาแล้วให้เลื่อน PatientConsent::NOTICE_VERSION — คนไข้จะถูกแจ้งฉบับใหม่ตอนมาครั้งถัดไป
 * 'p' = ย่อหน้า · 'li' = รายการย่อย (เยื้อง) · ข้อความใน [[ ]] = ต้องให้ DPO/หัวหน้ายืนยันก่อนประกาศจริง
 */

$addrTh = '143 ถนนศรีดอนไชย ตำบลช้างคลาน อำเภอเมืองเชียงใหม่ จังหวัดเชียงใหม่ 50100';
$addrEn = '143 Sridonchai Road, Chang Khlan, Mueang Chiang Mai, Chiang Mai 50100, Thailand';

return [
    'th' => [
        'title'    => 'คำประกาศเกี่ยวกับความเป็นส่วนตัว (Privacy Notice)',
        'subtitle' => 'ของศูนย์บริการเวชศาสตร์ป้องกัน สำนักงานป้องกันควบคุมโรคที่ 1 จังหวัดเชียงใหม่',
        'intro' => [
            'กรมควบคุมโรค โดยศูนย์บริการเวชศาสตร์ป้องกัน สำนักงานป้องกันควบคุมโรคที่ 1 จังหวัดเชียงใหม่ ตระหนักถึงความสำคัญของการคุ้มครองข้อมูลส่วนบุคคลของผู้รับบริการ และถือปฏิบัติอย่างเคร่งครัดในเรื่องการเคารพสิทธิความเป็นส่วนตัวของผู้รับบริการเป็นสำคัญ',
            'คำประกาศฉบับนี้จัดทำขึ้นเพื่อให้ท่านในฐานะผู้รับบริการของคลินิกโรคติดต่อทางเพศสัมพันธ์ (STI) คลินิกวัณโรค (TB) และคลินิกเวชศาสตร์การเดินทางและท่องเที่ยว (TMC) ได้ทราบและเข้าใจถึงวิธีการที่กรมควบคุมโรคเก็บรวบรวม ใช้ หรือเปิดเผยข้อมูลส่วนบุคคล (รวมเรียกว่า “ประมวลผล”) ของท่าน ประเภทข้อมูล วัตถุประสงค์ ระยะเวลาการเก็บรักษา สิทธิของท่าน และช่องทางการติดต่อ โดยกรมควบคุมโรคดำเนินการในฐานะผู้ควบคุมข้อมูลส่วนบุคคล (Data Controller)',
            'ประกาศนี้ใช้ประกอบกับนโยบายการคุ้มครองข้อมูลส่วนบุคคล (Privacy Policy) กรมควบคุมโรค ประกาศ ณ วันที่ 17 กุมภาพันธ์ 2568 (อ่านได้ที่ www.ddc.moph.go.th) หากข้อความในประกาศนี้ขัดแย้งกับนโยบายดังกล่าวในสาระสำคัญ ให้ถือตามประกาศนี้',
        ],
        'sections' => [
            ['h' => '1. คำนิยาม', 'body' => [
                ['p', '“ศูนย์ฯ” หมายความว่า ศูนย์บริการเวชศาสตร์ป้องกัน สำนักงานป้องกันควบคุมโรคที่ 1 จังหวัดเชียงใหม่ กรมควบคุมโรค'],
                ['p', '“ผู้รับบริการ” หมายความว่า บุคคลที่มารับบริการตรวจ รักษา ให้คำปรึกษา รับวัคซีน หรือรับบริการอื่นของศูนย์ฯ รวมถึงผู้แทนโดยชอบธรรมของบุคคลดังกล่าว'],
                ['p', '“ข้อมูลสุขภาพ” หมายความว่า ข้อมูลเกี่ยวกับสุขภาพ การเจ็บป่วย พฤติกรรมทางเพศ ผลการตรวจ การวินิจฉัย การรักษา และการได้รับวัคซีน ซึ่งเป็นข้อมูลส่วนบุคคลที่มีความอ่อนไหวตามมาตรา 26 แห่งพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562'],
                ['p', '“คู่สัมผัส” หมายความว่า คู่นอนหรือผู้ที่สัมผัสใกล้ชิดกับผู้รับบริการ ซึ่งมีความเสี่ยงต่อการติดโรคเดียวกัน'],
            ]],
            ['h' => '2. ฐานกฎหมายในการประมวลผลข้อมูลส่วนบุคคล', 'body' => [
                ['p', 'กรมควบคุมโรคประมวลผลข้อมูลส่วนบุคคลของท่านภายใต้ฐานกฎหมาย ดังต่อไปนี้'],
                ['li', '2.1 ความจำเป็นในการดำเนินงานตามภารกิจสาธารณะและการใช้อำนาจรัฐ ในการเฝ้าระวัง ป้องกัน และควบคุมโรค ตามกฎกระทรวงแบ่งส่วนราชการกรมควบคุมโรค กระทรวงสาธารณสุข และพระราชบัญญัติโรคติดต่อ พ.ศ. 2558'],
                ['li', '2.2 ความจำเป็นเพื่อการปฏิบัติหน้าที่ตามกฎหมาย ได้แก่ การแจ้งและรายงานโรคติดต่อที่ต้องเฝ้าระวังตามพระราชบัญญัติโรคติดต่อ พ.ศ. 2558 การออกเอกสารรับรองการได้รับวัคซีนตามกฎอนามัยระหว่างประเทศ (International Health Regulations 2005) และการรักษาความลับข้อมูลสุขภาพตามมาตรา 7 แห่งพระราชบัญญัติสุขภาพแห่งชาติ พ.ศ. 2550'],
                ['li', '2.3 สำหรับข้อมูลสุขภาพ: ความจำเป็นเพื่อประโยชน์ด้านเวชศาสตร์ป้องกัน การวินิจฉัยโรคทางการแพทย์ และการให้บริการด้านสุขภาพ โดยผู้ประกอบวิชาชีพซึ่งมีหน้าที่รักษาความลับ และเพื่อประโยชน์สาธารณะด้านการสาธารณสุข เช่น การป้องกันโรคติดต่ออันตรายหรือโรคระบาด ตามมาตรา 26 (5) (ก) และ (ข) แห่งพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562'],
                ['li', '2.4 ความจำเป็นในการป้องกันหรือระงับอันตรายต่อชีวิต ร่างกาย หรือสุขภาพของท่านหรือบุคคลอื่น ในกรณีฉุกเฉินที่ท่านไม่สามารถให้ความยินยอมได้'],
                ['li', '2.5 ความยินยอมของท่าน เฉพาะเรื่องที่ระบุในข้อ 3.8 ซึ่งท่านเลือกให้หรือไม่ให้ความยินยอมแยกเป็นรายเรื่องได้ และถอนความยินยอมได้ตลอดเวลา การไม่ให้ความยินยอมไม่มีผลต่อสิทธิในการรับการรักษาของท่าน'],
            ]],
            ['h' => '3. วัตถุประสงค์ในการเก็บรวบรวม ใช้ หรือเปิดเผยข้อมูลส่วนบุคคลของท่าน', 'body' => [
                ['p', 'กรมควบคุมโรคดำเนินการเก็บรวบรวมข้อมูลส่วนบุคคลของท่านเพื่อวัตถุประสงค์ ดังต่อไปนี้'],
                ['li', '3.1 ลงทะเบียนผู้รับบริการ ออกเลขประจำตัวผู้ป่วย (HN) และยืนยันตัวตนก่อนให้บริการ'],
                ['li', '3.2 ซักประวัติ ตรวจร่างกาย ตรวจทางห้องปฏิบัติการ วินิจฉัย รักษา ให้คำปรึกษา ให้วัคซีน จ่ายยา และส่งต่อการรักษา'],
                ['li', '3.3 นัดหมาย ติดตามการรักษา ติดตามการกินยาวัณโรค (DOT) และติดตามผลการตรวจ'],
                ['li', '3.4 คิดค่าบริการ ออกใบเสร็จรับเงิน และเบิกจ่ายตามสิทธิการรักษาของท่าน'],
                ['li', '3.5 ออกเอกสารทางการแพทย์ เช่น ใบรับรองแพทย์ ใบนัด สมุดรับรองการได้รับวัคซีน (สมุดเหลือง) และใบสรุปรายการยา'],
                ['li', '3.6 รายงานโรคติดต่อและข้อมูลทางระบาดวิทยาต่อหน่วยงานที่เกี่ยวข้องตามที่กฎหมายกำหนด'],
                ['li', '3.7 จัดทำสถิติ ประเมินผล และพัฒนาคุณภาพบริการ โดยใช้ข้อมูลในรูปแบบที่ไม่สามารถระบุตัวบุคคลได้'],
                ['li', '3.8 เรื่องที่ต้องได้รับความยินยอมจากท่านก่อน ได้แก่ (1) ส่งข้อความแจ้งวันนัด เตือนนัด และแจ้งให้มาฟังผลทางแอปพลิเคชัน LINE โดยข้อความไม่ระบุชื่อโรคหรือผลตรวจ (2) เก็บรูปถ่ายจากบัตรประจำตัวประชาชนไว้ในเวชระเบียนเพื่อยืนยันตัวตน (3) ติดต่อคู่สัมผัสที่ท่านให้ข้อมูลไว้เพื่อแนะนำให้มารับการตรวจ โดยไม่เปิดเผยชื่อของท่าน (4) ใช้ข้อมูลในโครงการวิจัย ซึ่งจะขอความยินยอมแยกตามแบบฟอร์มของแต่ละโครงการ'],
                ['li', '3.9 กรณีผู้รับบริการเป็นผู้เยาว์ เรื่องที่ต้องขอความยินยอมตามข้อ 3.8 จะขอความยินยอมจากผู้ใช้อำนาจปกครอง ตามข้อ 9 ของนโยบายการคุ้มครองข้อมูลส่วนบุคคล กรมควบคุมโรค ทั้งนี้ ไม่กระทบต่อการให้บริการตรวจรักษา ซึ่งไม่ต้องอาศัยความยินยอม'],
                ['p', 'ในกรณีที่กรมควบคุมโรคเก็บรวบรวมข้อมูลส่วนบุคคลของท่านเพื่อให้บรรลุวัตถุประสงค์ตามอำนาจหน้าที่ที่กฎหมายกำหนด ท่านมีหน้าที่ต้องให้ข้อมูลที่จำเป็นแก่กรมควบคุมโรค การปฏิเสธไม่ให้ข้อมูลหรือการให้ข้อมูลไม่ถูกต้องครบถ้วน อาจทำให้ศูนย์ฯ ไม่สามารถให้บริการแก่ท่านได้อย่างปลอดภัยและเหมาะสม'],
            ]],
            ['h' => '4. ประเภทของข้อมูลส่วนบุคคลที่เก็บรวบรวมและใช้', 'body' => [
                ['p', 'ตามวัตถุประสงค์ข้างต้น กรมควบคุมโรคเก็บรวบรวม ใช้ หรือเปิดเผยข้อมูลส่วนบุคคลของท่าน ดังรายการต่อไปนี้'],
                ['li', '4.1 ข้อมูลส่วนตัว เช่น คำนำหน้า ชื่อ นามสกุล (ภาษาไทยและภาษาอังกฤษ) เพศ วันเดือนปีเกิด อายุ สัญชาติ เลขประจำตัวประชาชน เลขหนังสือเดินทาง เลขประจำตัวผู้ป่วย (HN) และรูปถ่าย (เมื่อได้รับความยินยอม)'],
                ['li', '4.2 ข้อมูลการติดต่อ เช่น ที่อยู่ หมายเลขโทรศัพท์ และรหัสผู้ใช้ LINE (เมื่อท่านผูกบัญชีและให้ความยินยอม)'],
                ['li', '4.3 ข้อมูลเกี่ยวกับอาชีพและกลุ่มประชากร เช่น อาชีพ สิทธิการรักษา และกลุ่มประชากรที่ใช้ในการรายงานโรค'],
                ['li', '4.4 ข้อมูลสุขภาพ (ข้อมูลอ่อนไหว) เช่น ประวัติการเจ็บป่วย ประวัติแพ้ยา โรคประจำตัว พฤติกรรมเสี่ยง ผลการตรวจร่างกายและทางห้องปฏิบัติการ (รวมถึงผลการตรวจการติดเชื้อเอชไอวี) การวินิจฉัย การรักษา ยา วัคซีน ประวัติการเดินทาง และข้อมูลคู่สัมผัสที่ท่านให้ไว้'],
                ['li', '4.5 ข้อมูลด้านเทคนิค เช่น บันทึกการเข้าถึงและแก้ไขเวชระเบียนของเจ้าหน้าที่ และบันทึกการส่งข้อความทาง LINE'],
                ['li', '4.6 ข้อมูลส่วนตัวอื่น ๆ ตามที่ประกาศหรือกฎหมายที่เกี่ยวข้องกำหนด'],
            ]],
            ['h' => '5. การเปิดเผยข้อมูลส่วนบุคคลของท่าน', 'body' => [
                ['p', 'กรมควบคุมโรคจะเก็บรักษาข้อมูลที่เกี่ยวกับท่านไว้เป็นความลับ และจะไม่เปิดเผย เว้นแต่กรณีที่มีความจำเป็นต้องเปิดเผยข้อมูลส่วนบุคคลของท่านแก่บุคคลหรือหน่วยงาน ดังนี้'],
                ['li', '5.1 หน่วยงานในสังกัดกรมควบคุมโรคและกระทรวงสาธารณสุข เพื่อการรายงานโรคติดต่อและการเฝ้าระวังทางระบาดวิทยาตามที่กฎหมายกำหนด'],
                ['li', '5.2 สถานพยาบาลที่รับส่งต่อ และห้องปฏิบัติการภายนอกที่ศูนย์ฯ ส่งตัวอย่างไปตรวจ เฉพาะข้อมูลที่จำเป็นต่อการรักษาหรือการตรวจ'],
                ['li', '5.3 สำนักงานหลักประกันสุขภาพแห่งชาติ หรือหน่วยงานผู้รับผิดชอบสิทธิการรักษาของท่าน เพื่อการเบิกจ่ายค่าบริการ'],
                ['li', '5.4 ผู้ให้บริการระบบส่งข้อความ (LINE) เฉพาะกรณีที่ท่านให้ความยินยอม โดยส่งเฉพาะข้อความนัดหมายที่ไม่ระบุชื่อโรคหรือผลตรวจ'],
                ['li', '5.5 สำนักงานคณะกรรมการคุ้มครองข้อมูลส่วนบุคคล หน่วยงานบังคับใช้กฎหมาย และหน่วยงานในกระบวนการยุติธรรม เมื่อมีหน้าที่ตามกฎหมายหรือมีคำสั่งโดยชอบด้วยกฎหมาย'],
                ['li', '5.6 การเปิดเผยข้อมูลส่วนบุคคลของท่านให้กับบุคคลหรือหน่วยงานอื่น จะดำเนินการภายใต้วัตถุประสงค์ที่กำหนดหรือวัตถุประสงค์อื่นที่กฎหมายกำหนดให้กระทำได้เท่านั้น ในกรณีที่กฎหมายกำหนดว่าต้องได้รับความยินยอมจากท่าน กรมควบคุมโรคจะขอความยินยอมจากท่านก่อน'],
                ['li', '5.7 ศูนย์ฯ จะไม่เปิดเผยข้อมูลของท่านให้แก่คู่สัมผัส ครอบครัว นายจ้าง หรือสถานศึกษา เว้นแต่ได้รับความยินยอมจากท่าน หรือเป็นกรณีที่กฎหมายกำหนด'],
            ]],
            ['h' => '6. สิทธิของเจ้าของข้อมูลส่วนบุคคล', 'body' => [
                ['p', 'ท่านสามารถใช้สิทธิตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 ดังต่อไปนี้'],
                ['li', '6.1 สิทธิในการขอเพิกถอนความยินยอม ในเรื่องที่ท่านได้ให้ความยินยอมไว้ (ข้อ 3.8) ได้ตลอดเวลา โดยแจ้งเจ้าหน้าที่ ณ จุดลงทะเบียน'],
                ['li', '6.2 สิทธิในการขอเข้าถึงและขอรับสำเนาข้อมูลส่วนบุคคลของท่าน รวมถึงขอให้เปิดเผยการได้มาซึ่งข้อมูลดังกล่าว'],
                ['li', '6.3 สิทธิในการขอให้โอนย้ายข้อมูลส่วนบุคคล ในกรณีที่สามารถทำได้ด้วยวิธีการอัตโนมัติ'],
                ['li', '6.4 สิทธิในการคัดค้านการเก็บรวบรวม ใช้ หรือเปิดเผยข้อมูลส่วนบุคคล ในกรณีที่กฎหมายกำหนด'],
                ['li', '6.5 สิทธิในการขอแก้ไขข้อมูลส่วนบุคคลให้ถูกต้อง เป็นปัจจุบัน สมบูรณ์ และไม่ก่อให้เกิดความเข้าใจผิด'],
                ['li', '6.6 สิทธิในการขอให้ลบ ทำลาย หรือทำให้ข้อมูลไม่สามารถระบุตัวบุคคลได้ ในบางกรณี'],
                ['li', '6.7 สิทธิในการขอให้ระงับการใช้ข้อมูลส่วนบุคคล ในบางกรณี'],
                ['p', 'ในกรณีที่ท่านประสงค์จะใช้สิทธิดังกล่าว ท่านสามารถติดต่อตามช่องทางในข้อ 12 อย่างไรก็ดี กรมควบคุมโรคอาจปฏิเสธการใช้สิทธิได้ตามหลักเกณฑ์ที่กฎหมายกำหนด เช่น ข้อมูลเวชระเบียนที่ต้องเก็บรักษาตามกฎหมาย โดยจะแจ้งเหตุผลของการปฏิเสธให้ท่านทราบ'],
            ]],
            ['h' => '7. การส่งหรือโอนข้อมูลส่วนบุคคลของท่านไปยังต่างประเทศ', 'body' => [
                ['p', 'ศูนย์ฯ เก็บเวชระเบียนไว้ในเครื่องแม่ข่ายภายในสำนักงาน และไม่ส่งข้อมูลสุขภาพของท่านไปยังต่างประเทศ ทั้งนี้ ข้อความแจ้งนัดทางแอปพลิเคชัน LINE อาจถูกประมวลผลผ่านเครื่องแม่ข่ายของผู้ให้บริการที่ตั้งอยู่ต่างประเทศ ศูนย์ฯ จะส่งข้อความดังกล่าวเฉพาะเมื่อได้รับความยินยอมจากท่าน และจะไม่ระบุชื่อโรคหรือผลตรวจในข้อความ'],
            ]],
            ['h' => '8. ระยะเวลาในการเก็บรักษาข้อมูลส่วนบุคคล', 'body' => [
                ['p', 'กรมควบคุมโรคจะเก็บรักษาข้อมูลส่วนบุคคลของท่านตามระยะเวลาที่จำเป็นเพื่อบรรลุวัตถุประสงค์ ดังนี้'],
                ['li', '8.1 เวชระเบียนและข้อมูลการรักษา เก็บไว้ 5 ปีนับจากวันที่มารับบริการครั้งสุดท้าย เมื่อครบกำหนดจะทำลาย'],
                ['li', '8.2 รูปถ่ายและรหัสผู้ใช้ LINE เก็บไว้จนกว่าท่านจะถอนความยินยอม หรือยกเลิกการผูกบัญชี LINE'],
                ['li', '8.3 บันทึกการให้หรือถอนความยินยอม เก็บไว้ตลอดระยะเวลาที่เก็บเวชระเบียน เพื่อเป็นหลักฐานตามกฎหมาย'],
                ['p', 'เมื่อพ้นกำหนดระยะเวลาดังกล่าว กรมควบคุมโรคจะลบหรือทำลายข้อมูลส่วนบุคคล หรือทำให้ข้อมูลเป็นข้อมูลที่ไม่สามารถระบุตัวตนได้ เว้นแต่ข้อมูลดังกล่าวจำเป็นต่อการก่อตั้งสิทธิเรียกร้องทางกฎหมาย หรือกรณีมีข้อพิพาทจนสิ้นสุดระยะเวลาบังคับคดีตามกฎหมาย'],
            ]],
            ['h' => '9. การรักษาความมั่นคงปลอดภัยข้อมูลส่วนบุคคล', 'body' => [
                ['p', 'กรมควบคุมโรคมีมาตรการในการรักษาความมั่นคงปลอดภัยข้อมูลส่วนบุคคลของท่านอย่างเหมาะสม ทั้งในเชิงเทคนิคและการบริหารจัดการ เพื่อป้องกันมิให้ข้อมูลสูญหาย หรือมีการเข้าถึง ลบ ทำลาย ใช้ เปลี่ยนแปลง แก้ไข หรือเปิดเผยโดยไม่ได้รับอนุญาต ซึ่งสอดคล้องกับนโยบายและแนวปฏิบัติด้านความมั่นคงปลอดภัยสารสนเทศของกรมควบคุมโรค ได้แก่'],
                ['li', '• ระบบเวชระเบียนติดตั้งบนเครื่องแม่ข่ายภายในสำนักงาน ไม่เก็บข้อมูลผู้รับบริการบนบริการคลาวด์สาธารณะ'],
                ['li', '• เจ้าหน้าที่ต้องเข้าสู่ระบบด้วยบัญชีของตนเอง และเข้าถึงข้อมูลได้ตามหน้าที่ที่ได้รับมอบหมายเท่านั้น'],
                ['li', '• รูปถ่ายและเอกสารแนบถูกจัดเก็บแบบเข้ารหัส และระบบบันทึกประวัติการแก้ไขข้อมูล'],
            ]],
            ['h' => '10. ความรับผิดชอบของผู้ควบคุมข้อมูลส่วนบุคคล', 'body' => [
                ['p', 'กรมควบคุมโรคได้กำหนดให้เจ้าหน้าที่เฉพาะผู้ที่มีอำนาจหน้าที่เกี่ยวข้องในการให้บริการแก่ท่านเท่านั้นที่จะสามารถเข้าถึงข้อมูลส่วนบุคคลของท่านได้ โดยเจ้าหน้าที่ทุกคนมีหน้าที่รักษาความลับของผู้รับบริการ และต้องปฏิบัติตามประกาศนี้อย่างเคร่งครัด'],
            ]],
            ['h' => '11. การเปลี่ยนแปลงแก้ไขคำประกาศเกี่ยวกับความเป็นส่วนตัว', 'body' => [
                ['p', 'กรมควบคุมโรคอาจปรับปรุงประกาศนี้เป็นครั้งคราว เพื่อให้เหมาะสม เป็นปัจจุบัน และสอดคล้องกับกฎหมาย หากมีการแก้ไข ศูนย์ฯ จะแสดงประกาศฉบับล่าสุด ณ จุดลงทะเบียน ผ่านคิวอาร์โค้ดในพื้นที่ให้บริการ และแจ้งให้ท่านทราบเมื่อท่านมารับบริการครั้งถัดไป'],
            ]],
            ['h' => '12. การติดต่อสอบถาม', 'body' => [
                ['p', 'ท่านสามารถติดต่อสอบถามเกี่ยวกับประกาศฉบับนี้ หรือขอใช้สิทธิตามข้อ 6 ได้ที่'],
                ['li', '12.1 ผู้ควบคุมข้อมูลส่วนบุคคล (Data Controller): กรมควบคุมโรค เลขที่ 88/21 ถนนติวานนท์ ตำบลตลาดขวัญ อำเภอเมือง จังหวัดนนทบุรี 11000 โทรศัพท์ 0-2590-3000'],
                ['li', '12.2 เจ้าหน้าที่คุ้มครองข้อมูลส่วนบุคคล (DPO) กรมควบคุมโรค: กลุ่มวิทยาการข้อมูลและการบริหารจัดการสารสนเทศ กองดิจิทัลเพื่อการควบคุมโรค กรมควบคุมโรค เลขที่ 88/21 อาคาร 2 ชั้น 3 ถนนติวานนท์ ตำบลตลาดขวัญ อำเภอเมือง จังหวัดนนทบุรี 11000 โทรศัพท์ 0-2590-3926 อีเมล dpo@ddc.mail.go.th'],
                ['li', "12.3 ศูนย์บริการเวชศาสตร์ป้องกัน สำนักงานป้องกันควบคุมโรคที่ 1 จังหวัดเชียงใหม่: {$addrTh} โทรศัพท์ 053-140774 ต่อ 600-604 อีเมล clinicdpc1cm@gmail.com"],
            ]],
        ],
        'issued' => 'ประกาศ ณ วันที่ 1 พฤศจิกายน 2569',
    ],

    'en' => [
        'title'    => 'Privacy Notice',
        'subtitle' => 'Preventive Medicine Service Center, Office of Disease Prevention and Control Region 1 Chiang Mai',
        'intro' => [
            'The Department of Disease Control (DDC), through the Preventive Medicine Service Center of the Office of Disease Prevention and Control Region 1 Chiang Mai, values the protection of your personal data and respects your privacy.',
            'This notice explains how we collect, use and disclose (“process”) the personal data of people who use our Sexually Transmitted Infection (STI) clinic, Tuberculosis (TB) clinic and Travel Medicine Clinic (TMC): what data we hold, why, how long we keep it, your rights, and how to contact us. The DDC is the Data Controller.',
            'This notice supplements the DDC Personal Data Protection Policy dated 17 February 2025 (available at www.ddc.moph.go.th). Where the two differ materially, this notice prevails.',
        ],
        'sections' => [
            ['h' => '1. Definitions', 'body' => [
                ['p', '“The Center” means the Preventive Medicine Service Center, Office of Disease Prevention and Control Region 1 Chiang Mai, Department of Disease Control.'],
                ['p', '“Health data” means data about your health, illness, sexual behaviour, test results, diagnosis, treatment and vaccinations. It is sensitive personal data under Section 26 of the Personal Data Protection Act B.E. 2562 (2019).'],
                ['p', '“Contact” means a sexual partner or close contact who may be at risk of the same infection.'],
            ]],
            ['h' => '2. Legal basis', 'body' => [
                ['li', '2.1 Public task and official authority to monitor, prevent and control disease under the DDC organisation regulations and the Communicable Diseases Act B.E. 2558 (2015).'],
                ['li', '2.2 Legal obligations, including notifiable-disease reporting under the Communicable Diseases Act, vaccination certificates under the International Health Regulations (2005), and confidentiality of health information under Section 7 of the National Health Act B.E. 2550 (2007).'],
                ['li', '2.3 For health data: preventive medicine, medical diagnosis and health care by professionals bound by confidentiality, and public interest in public health, under Section 26 (5) (a) and (b) of the Personal Data Protection Act.'],
                ['li', '2.4 Preventing or suppressing a danger to life, body or health in an emergency when you cannot give consent.'],
                ['li', '2.5 Your consent, only for the purposes in 3.8. You may agree or refuse each one separately and withdraw at any time. Refusing does not affect your right to treatment.'],
            ]],
            ['h' => '3. Purposes', 'body' => [
                ['li', '3.1 Registering you, issuing a hospital number (HN) and confirming your identity.'],
                ['li', '3.2 Taking your history, examination, laboratory tests, diagnosis, treatment, counselling, vaccination, dispensing medicine and referral.'],
                ['li', '3.3 Appointments and follow-up, including directly observed TB treatment (DOT) and test results.'],
                ['li', '3.4 Charging for services, issuing receipts and claiming under your health coverage.'],
                ['li', '3.5 Issuing medical documents such as medical certificates, appointment cards, the International Certificate of Vaccination (yellow book) and medication summaries.'],
                ['li', '3.6 Reporting communicable diseases and epidemiological data as required by law.'],
                ['li', '3.7 Statistics, evaluation and service improvement using data that cannot identify you.'],
                ['li', '3.8 With your consent only: (1) appointment and result reminders via LINE, which never mention a disease or result; (2) keeping the photo from your national ID card in your record to confirm your identity; (3) contacting the partners you name, without revealing your name, to advise them to get tested; (4) research, using each project’s own consent form.'],
                ['li', '3.9 For minors, consent for the purposes in 3.8 is obtained from the person exercising parental authority, in line with section 9 of the DDC Personal Data Protection Policy. This does not affect examination and treatment, which do not rely on consent.'],
            ]],
            ['h' => '4. Data we collect', 'body' => [
                ['li', '4.1 Identity: title, name and surname (Thai and English), sex, date of birth, age, nationality, national ID number, passport number, HN, and photo (with consent).'],
                ['li', '4.2 Contact: address, telephone number and LINE user ID (if you link LINE and consent).'],
                ['li', '4.3 Occupation, health coverage and population group used for disease reporting.'],
                ['li', '4.4 Health data (sensitive): medical history, drug allergies, chronic conditions, risk behaviour, examination and laboratory results (including HIV), diagnosis, treatment, medicines, vaccines, travel history and partner information you provide.'],
                ['li', '4.5 Technical: records of staff access to and changes in your record, and LINE message logs.'],
            ]],
            ['h' => '5. Disclosure', 'body' => [
                ['p', 'We keep your data confidential and disclose it only to:'],
                ['li', '5.1 DDC and Ministry of Public Health units, for legally required disease reporting and surveillance.'],
                ['li', '5.2 Hospitals we refer you to, and external laboratories that test your samples, limited to what they need.'],
                ['li', '5.3 The National Health Security Office or your coverage provider, for reimbursement.'],
                ['li', '5.4 The LINE messaging provider, only with your consent and only for appointment messages without diagnosis or results.'],
                ['li', '5.5 The Personal Data Protection Committee, law-enforcement agencies and courts, where required by law.'],
                ['li', '5.6 We never disclose your data to your partners, family, employer or school without your consent, unless the law requires it.'],
            ]],
            ['h' => '6. Your rights', 'body' => [
                ['li', '6.1 Withdraw consent for any purpose in 3.8 at any time, at the registration desk.'],
                ['li', '6.2 Access and obtain a copy of your data, and ask how it was obtained.'],
                ['li', '6.3 Data portability, where technically possible.'],
                ['li', '6.4 Object to processing, where the law allows.'],
                ['li', '6.5 Correct inaccurate or incomplete data.'],
                ['li', '6.6 Erasure, destruction or anonymisation, in certain cases.'],
                ['li', '6.7 Restrict use of your data, in certain cases.'],
                ['p', 'Contact us using section 12. We may refuse a request where the law allows, for example for medical records we must keep, and we will tell you why.'],
            ]],
            ['h' => '7. Transfers abroad', 'body' => [
                ['p', 'Medical records are kept on a server inside our office and health data is not sent abroad. LINE reminder messages may be processed on the provider’s servers outside Thailand; we send them only with your consent and they never contain a diagnosis or result.'],
            ]],
            ['h' => '8. Retention', 'body' => [
                ['li', '8.1 Medical records: 5 years after your last visit, then destroyed.'],
                ['li', '8.2 Photo and LINE user ID: until you withdraw consent or unlink LINE.'],
                ['li', '8.3 Consent records: for as long as the medical record is kept, as legal evidence.'],
            ]],
            ['h' => '9. Security', 'body' => [
                ['p', 'We apply technical and organisational safeguards in line with DDC information-security policy: the record system runs on an in-office server rather than a public cloud; staff log in with personal accounts and see only what their role needs; photos and attachments are stored encrypted; and changes to records are logged.'],
            ]],
            ['h' => '10. Accountability', 'body' => [
                ['p', 'Only staff involved in your care can access your data. All staff have a duty of confidentiality and must follow this notice.'],
            ]],
            ['h' => '11. Changes to this notice', 'body' => [
                ['p', 'We may update this notice. The latest version is displayed at the registration desk and via the QR code in the service area, and we will inform you at your next visit.'],
            ]],
            ['h' => '12. Contact', 'body' => [
                ['li', '12.1 Data Controller: Department of Disease Control, 88/21 Tiwanon Road, Talat Khwan, Mueang, Nonthaburi 11000. Tel. +66 2590 3000'],
                ['li', '12.2 Data Protection Officer, DDC: Data Science and Information Management Group, Division of Digital Disease Control, Building 2, 3rd floor, 88/21 Tiwanon Road, Talat Khwan, Mueang, Nonthaburi 11000. Tel. +66 2590 3926, email dpo@ddc.mail.go.th'],
                ['li', "12.3 Preventive Medicine Service Center, ODPC 1 Chiang Mai: {$addrEn}. Tel. +66 53 140 774 ext. 600-604, email clinicdpc1cm@gmail.com"],
            ]],
        ],
        'issued' => 'Issued on 1 November 2026',
    ],
];
