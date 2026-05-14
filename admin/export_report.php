<?php
session_start();
if (!isset($_SESSION['user_id'])||!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true){header('Location:../login.php');exit;}
require_once __DIR__.'/../pathway_strand_catalog.php';
require_once __DIR__ . '/../db.php';

$conn = db_connect();

$sy_r=$conn->query("SELECT setting_value FROM system_settings WHERE setting_key='academic_year' LIMIT 1");
$current_sy=($sy_r&&$r=$sy_r->fetch_assoc())?$r['setting_value']:'';
$w=$current_sy?" AND school_year='".$conn->real_escape_string($current_sy)."'":'';

$report=$_GET['report']??'';

// ── Simple XLSX Writer ──
class SimpleXLSX {
    private $sheets=[];
    public function addSheet($name,$headers,$rows){
        $this->sheets[]=compact('name','headers','rows');
    }
    private function esc($v){return htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');}
    private function colLetter($i){$l='';while($i>=0){$l=chr(65+($i%26)).$l;$i=intdiv($i,26)-1;}return $l;}
    public function output($filename){
        $tmp=tempnam(sys_get_temp_dir(),'xlsx');
        $zip=new ZipArchive();
        $zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE);

        // [Content_Types].xml
        $ct='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>';
        foreach($this->sheets as $i=>$s) $ct.='<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $ct.='<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>';
        $zip->addFromString('[Content_Types].xml',$ct);

        // _rels/.rels
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');

        // xl/_rels/workbook.xml.rels
        $wbr='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach($this->sheets as $i=>$s) $wbr.='<Relationship Id="rId'.($i+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i+1).'.xml"/>';
        $wbr.='<Relationship Id="rId'.($c=count($this->sheets)+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $wbr.='<Relationship Id="rId'.($c+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $wbr.='</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels',$wbr);

        // Collect shared strings
        $ss=[];$ssIdx=[];
        foreach($this->sheets as $s){
            foreach($s['headers'] as $h){if(!isset($ssIdx[$h])){$ssIdx[$h]=count($ss);$ss[]=$h;}}
            foreach($s['rows'] as $row){foreach($row as $v){$sv=(string)$v;if(!is_numeric($v)&&!isset($ssIdx[$sv])){$ssIdx[$sv]=count($ss);$ss[]=$sv;}}}
        }

        // xl/sharedStrings.xml
        $ssx='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($ss).'" uniqueCount="'.count($ss).'">';
        foreach($ss as $s) $ssx.='<si><t>'.$this->esc($s).'</t></si>';
        $ssx.='</sst>';
        $zip->addFromString('xl/sharedStrings.xml',$ssx);

        // xl/styles.xml - bold header style
        $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF009B5A"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>');

        // xl/workbook.xml
        $wb='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach($this->sheets as $i=>$s) $wb.='<sheet name="'.$this->esc($s['name']).'" sheetId="'.($i+1).'" r:id="rId'.($i+1).'"/>';
        $wb.='</sheets></workbook>';
        $zip->addFromString('xl/workbook.xml',$wb);

        // Worksheets
        foreach($this->sheets as $si=>$s){
            $maxCol=$this->colLetter(count($s['headers'])-1);
            $maxRow=count($s['rows'])+1;
            $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$maxCol.$maxRow.'"/><sheetData>';
            // Header row
            $xml.='<row r="1">';
            foreach($s['headers'] as $ci=>$h) $xml.='<c r="'.$this->colLetter($ci).'1" t="s" s="1"><v>'.$ssIdx[$h].'</v></c>';
            $xml.='</row>';
            // Data rows
            foreach($s['rows'] as $ri=>$row){
                $rn=$ri+2;
                $xml.='<row r="'.$rn.'">';
                foreach(array_values($row) as $ci=>$v){
                    $ref=$this->colLetter($ci).$rn;
                    if(is_numeric($v)&&$v!=='') $xml.='<c r="'.$ref.'"><v>'.$v.'</v></c>';
                    else $xml.='<c r="'.$ref.'" t="s"><v>'.($ssIdx[(string)$v]??0).'</v></c>';
                }
                $xml.='</row>';
            }
            $xml.='</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet'.($si+1).'.xml',$xml);
        }

        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }
}

$xlsx=new SimpleXLSX();

// ══════════════════════════════════════
// REPORT: Enrollment Summary
// ══════════════════════════════════════
if($report==='enrollment_summary'){
    // Grade level
    $g11=0;$g12=0;
    $r=$conn->query("SELECT grade_level,COUNT(*) c FROM students WHERE enrollment_status!='withdrawn' $w GROUP BY grade_level");
    if($r) while($row=$r->fetch_assoc()){if(stripos($row['grade_level'],'11')!==false)$g11=(int)$row['c'];if(stripos($row['grade_level'],'12')!==false)$g12=(int)$row['c'];}
    $male=0;$female=0;
    $r=$conn->query("SELECT sex,COUNT(*) c FROM students WHERE enrollment_status!='withdrawn' $w GROUP BY sex");
    if($r) while($row=$r->fetch_assoc()){if($row['sex']==='Male')$male=(int)$row['c'];else $female=(int)$row['c'];}
    $eval=0;$enc=0;$enr=0;$wd=0;
    $r=$conn->query("SELECT enrollment_status,COUNT(*) c FROM students WHERE 1=1 $w GROUP BY enrollment_status");
    if($r) while($row=$r->fetch_assoc()){$s=$row['enrollment_status']??'';if($s==='enrolled')$enr=(int)$row['c'];elseif($s==='for_evaluation')$eval=(int)$row['c'];elseif($s==='for_encoding')$enc=(int)$row['c'];elseif($s==='withdrawn')$wd=(int)$row['c'];}

    $xlsx->addSheet('Summary',['Metric','Count'],[
        ['Total Active Students',$g11+$g12],['Grade 11',$g11],['Grade 12',$g12],
        ['Male',$male],['Female',$female],
        ['For Evaluation',$eval],['For Encoding',$enc],['Enrolled',$enr],['Withdrawn',$wd]
    ]);

    // Pathways
    $rows=[];
    $r=$conn->query("SELECT pathway_strand,sex,COUNT(*) c FROM students WHERE grade_level='Grade 11' AND enrollment_status!='withdrawn' $w GROUP BY pathway_strand,sex");
    $pw=[];if($r) while($row=$r->fetch_assoc()){$lb=get_pathway_strand_label('Grade 11',$row['pathway_strand'])?:$row['pathway_strand'];$pw[$lb][$row['sex']]=($pw[$lb][$row['sex']]??0)+(int)$row['c'];}
    foreach($pw as $lb=>$g) $rows[]=[$lb,$g['Male']??0,$g['Female']??0,($g['Male']??0)+($g['Female']??0)];
    $xlsx->addSheet('G11 Pathways',['Career Pathway','Male','Female','Total'],$rows);

    // Strands
    $rows=[];
    $r=$conn->query("SELECT pathway_strand,sex,COUNT(*) c FROM students WHERE grade_level='Grade 12' AND enrollment_status!='withdrawn' $w GROUP BY pathway_strand,sex");
    $st=[];if($r) while($row=$r->fetch_assoc()){$lb=get_pathway_strand_label('Grade 12',$row['pathway_strand'])?:$row['pathway_strand'];$st[$lb][$row['sex']]=($st[$lb][$row['sex']]??0)+(int)$row['c'];}
    foreach($st as $lb=>$g) $rows[]=[$lb,$g['Male']??0,$g['Female']??0,($g['Male']??0)+($g['Female']??0)];
    $xlsx->addSheet('G12 Strands',['Strand','Male','Female','Total'],$rows);

    // Student types
    $rows=[];
    $r=$conn->query("SELECT student_type,COUNT(*) c FROM students WHERE enrollment_status!='withdrawn' $w GROUP BY student_type ORDER BY c DESC");
    if($r) while($row=$r->fetch_assoc()) $rows[]=[$row['student_type']?:'Unset',(int)$row['c']];
    $xlsx->addSheet('Student Types',['Category','Count'],$rows);

    // Daily
    $rows=[];$yr=date('Y');
    for($i=1;$i<=6;$i++){
        $dt="$yr-06-".str_pad($i,2,'0',STR_PAD_LEFT);
        $m=0;$f=0;
        $r=$conn->query("SELECT sex,COUNT(*) c FROM students WHERE DATE(created_at)='$dt' AND enrollment_status!='withdrawn' $w GROUP BY sex");
        if($r) while($row=$r->fetch_assoc()){if($row['sex']==='Male')$m=(int)$row['c'];else $f=(int)$row['c'];}
        $rows[]=["June $i",$m,$f,$m+$f];
    }
    $m=0;$f=0;
    $r=$conn->query("SELECT sex,COUNT(*) c FROM students WHERE DATE(created_at)>='$yr-06-07' AND MONTH(created_at)=6 AND enrollment_status!='withdrawn' $w GROUP BY sex");
    if($r) while($row=$r->fetch_assoc()){if($row['sex']==='Male')$m=(int)$row['c'];else $f=(int)$row['c'];}
    $rows[]=['Late (Jun 7+)',$m,$f,$m+$f];
    $xlsx->addSheet('Daily Enrollment',['Day','Male','Female','Total'],$rows);

    $xlsx->output('Enrollment_Summary_'.date('Y-m-d').'.xlsx');
}

// ══════════════════════════════════════
// REPORT: Full Student Master List
// ══════════════════════════════════════
elseif($report==='student_masterlist'){
    $rows=[];
    $r=$conn->query("SELECT * FROM students WHERE enrollment_status!='withdrawn' $w ORDER BY grade_level,last_name,first_name");
    if($r) while($row=$r->fetch_assoc()){
        $ps=$row['grade_level']==='Grade 11'?get_pathway_strand_label('Grade 11',$row['pathway_strand']):get_pathway_strand_label('Grade 12',$row['pathway_strand']);
        $rows[]=[$row['lrn'],$row['last_name'],$row['first_name'],$row['middle_name'],$row['extension_name'],$row['sex'],$row['birthdate'],$row['age'],$row['grade_level'],$row['track'],$ps?:$row['pathway_strand'],$row['student_type'],$row['enrollment_status'],$row['school_year'],$row['semester'],$row['assigned_section']??'',($row['barangay']?$row['barangay'].', ':'').($row['city']??''),$row['mother_contact'],$row['father_contact'],$row['guardian_contact'],$row['created_at']];
    }
    $xlsx->addSheet('Master List',['LRN','Last Name','First Name','Middle Name','Ext','Sex','Birthdate','Age','Grade Level','Track','Pathway/Strand','Student Type','Status','School Year','Semester','Section','Address','Mother Contact','Father Contact','Guardian Contact','Date Enrolled'],$rows);
    $xlsx->output('Student_Master_List_'.date('Y-m-d').'.xlsx');
}

// ══════════════════════════════════════
// REPORT: Classroom Master List (per section)
// ══════════════════════════════════════
elseif($report==='classroom_masterlist'){
    $sec_id=$_GET['section_id']??'';
    // Get all sections or specific
    $secQuery="SELECT id,name,grade_level FROM add_sections ORDER BY grade_level,name";
    if($sec_id) $secQuery="SELECT id,name,grade_level FROM add_sections WHERE id=".(int)$sec_id;
    $sections=[];
    $r=$conn->query($secQuery);
    if($r) while($row=$r->fetch_assoc()) $sections[]=$row;

    foreach($sections as $sec){
        $rows=[];$num=1;
        $r=$conn->query("SELECT s.* FROM students s WHERE s.assigned_section='".$conn->real_escape_string($sec['name'])."' AND s.enrollment_status!='withdrawn' $w ORDER BY s.sex DESC,s.last_name,s.first_name");
        if($r) while($row=$r->fetch_assoc()){
            $name=$row['last_name'].', '.$row['first_name'];
            if($row['middle_name']) $name.=' '.strtoupper(substr($row['middle_name'],0,1)).'.';
            if($row['extension_name']) $name.=' '.$row['extension_name'];
            $ps=$row['grade_level']==='Grade 11'?get_pathway_strand_label('Grade 11',$row['pathway_strand']):get_pathway_strand_label('Grade 12',$row['pathway_strand']);
            $rows[]=[$num++,$row['lrn'],$name,$row['sex'],$row['birthdate'],$row['age'],$row['track'],$ps?:$row['pathway_strand'],$row['student_type'],$row['enrollment_status']];
        }
        $sheetName=substr('G'.$sec['grade_level'].' '.$sec['name'],0,31);
        $xlsx->addSheet($sheetName,['No','LRN','Name','Sex','Birthdate','Age','Track','Pathway/Strand','Type','Status'],$rows);
    }
    if(empty($sections)){$xlsx->addSheet('No Sections',['Info'],[['No sections found']]);}
    $fn=$sec_id?'Classroom_Section_':'Classroom_All_Sections_';
    $xlsx->output($fn.date('Y-m-d').'.xlsx');
}

// ══════════════════════════════════════
// REPORT: Withdrawn Students
// ══════════════════════════════════════
elseif($report==='withdrawn_list'){
    $rows=[];
    $r=$conn->query("SELECT * FROM students WHERE enrollment_status='withdrawn' $w ORDER BY last_name,first_name");
    if($r) while($row=$r->fetch_assoc()){
        $name=$row['last_name'].', '.$row['first_name'];
        if($row['middle_name']) $name.=' '.strtoupper(substr($row['middle_name'],0,1)).'.';
        $ps=$row['grade_level']==='Grade 11'?get_pathway_strand_label('Grade 11',$row['pathway_strand']):get_pathway_strand_label('Grade 12',$row['pathway_strand']);
        $rows[]=[$row['lrn'],$name,$row['sex'],$row['grade_level'],$row['track'],$ps?:$row['pathway_strand'],$row['student_type'],$row['created_at']];
    }
    $xlsx->addSheet('Withdrawn',['LRN','Name','Sex','Grade Level','Track','Pathway/Strand','Student Type','Date Enrolled'],$rows);
    $xlsx->output('Withdrawn_Students_'.date('Y-m-d').'.xlsx');
}

// ══════════════════════════════════════
// REPORT: Gender Analysis
// ══════════════════════════════════════
elseif($report==='gender_report'){
    // Overall
    $rows=[];
    $r=$conn->query("SELECT grade_level,sex,COUNT(*) c FROM students WHERE enrollment_status!='withdrawn' $w GROUP BY grade_level,sex ORDER BY grade_level,sex");
    if($r) while($row=$r->fetch_assoc()) $rows[]=[$row['grade_level'],$row['sex'],(int)$row['c']];
    $xlsx->addSheet('By Grade Level',['Grade Level','Sex','Count'],$rows);

    // By pathway
    $rows=[];
    $r=$conn->query("SELECT pathway_strand,sex,COUNT(*) c FROM students WHERE grade_level='Grade 11' AND enrollment_status!='withdrawn' $w GROUP BY pathway_strand,sex ORDER BY pathway_strand,sex");
    if($r) while($row=$r->fetch_assoc()){$lb=get_pathway_strand_label('Grade 11',$row['pathway_strand'])?:$row['pathway_strand'];$rows[]=[$lb,$row['sex'],(int)$row['c']];}
    $xlsx->addSheet('G11 Pathway Gender',['Career Pathway','Sex','Count'],$rows);

    // By strand
    $rows=[];
    $r=$conn->query("SELECT pathway_strand,sex,COUNT(*) c FROM students WHERE grade_level='Grade 12' AND enrollment_status!='withdrawn' $w GROUP BY pathway_strand,sex ORDER BY pathway_strand,sex");
    if($r) while($row=$r->fetch_assoc()){$lb=get_pathway_strand_label('Grade 12',$row['pathway_strand'])?:$row['pathway_strand'];$rows[]=[$lb,$row['sex'],(int)$row['c']];}
    $xlsx->addSheet('G12 Strand Gender',['Strand','Sex','Count'],$rows);

    // By type
    $rows=[];
    $r=$conn->query("SELECT student_type,sex,COUNT(*) c FROM students WHERE enrollment_status!='withdrawn' $w GROUP BY student_type,sex ORDER BY student_type,sex");
    if($r) while($row=$r->fetch_assoc()) $rows[]=[$row['student_type']?:'Unset',$row['sex'],(int)$row['c']];
    $xlsx->addSheet('By Student Type',['Category','Sex','Count'],$rows);

    $xlsx->output('Gender_Analysis_'.date('Y-m-d').'.xlsx');
}

else { die('Invalid report type.'); }
$conn->close();
?>
