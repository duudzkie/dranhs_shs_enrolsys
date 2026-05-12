<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) { header('Location: admin.php'); exit; }
require_once __DIR__ . '/../pathway_strand_catalog.php';
require_once __DIR__ . '/../db.php';

$dc = db_connect();
$d = ['g11'=>0,'g12'=>0,'male'=>0,'female'=>0,
      'enrolled'=>0,'for_eval'=>0,'for_enc'=>0,'withdrawn'=>0,
      'pathways_m'=>[],'pathways_f'=>[],'strands_m'=>[],'strands_f'=>[],
      'types'=>['Transferee'=>0,'Balik-Aral(Returnee)'=>0,'Repeater'=>0,'ALS'=>0,'Grade 10 DRANHS Student'=>0,'Old Student (Grade 11 Completer)'=>0,'Old Student (Repeater)'=>0],
      'daily_m'=>[],'daily_f'=>[]];
$current_sy='';
if(!$dc->connect_error){
  $r=$dc->query("SELECT setting_value FROM system_settings WHERE setting_key='academic_year' LIMIT 1");
  if($r&&$row=$r->fetch_assoc()) $current_sy=$row['setting_value'];
  $w=$current_sy?" AND school_year='".$dc->real_escape_string($current_sy)."'":'';

  // Grade level
  $r=$dc->query("SELECT grade_level,COUNT(*) c FROM students WHERE enrollment_status!='withdrawn' $w GROUP BY grade_level");
  if($r) while($row=$r->fetch_assoc()){
    if(stripos($row['grade_level'],'11')!==false) $d['g11']=(int)$row['c'];
    if(stripos($row['grade_level'],'12')!==false) $d['g12']=(int)$row['c'];
  }
  // Gender
  $r=$dc->query("SELECT sex,COUNT(*) c FROM students WHERE enrollment_status!='withdrawn' $w GROUP BY sex");
  if($r) while($row=$r->fetch_assoc()){
    if($row['sex']==='Male') $d['male']=(int)$row['c'];
    if($row['sex']==='Female') $d['female']=(int)$row['c'];
  }
  // Status
  $r=$dc->query("SELECT enrollment_status,COUNT(*) c FROM students WHERE 1=1 $w GROUP BY enrollment_status");
  if($r) while($row=$r->fetch_assoc()){
    $s=$row['enrollment_status']??'';
    if($s==='enrolled') $d['enrolled']=(int)$row['c'];
    elseif($s==='for_evaluation') $d['for_eval']=(int)$row['c'];
    elseif($s==='for_encoding') $d['for_enc']=(int)$row['c'];
    elseif($s==='withdrawn') $d['withdrawn']=(int)$row['c'];
  }
  // G11 Pathways by gender
  $r=$dc->query("SELECT pathway_strand,sex,COUNT(*) c FROM students WHERE grade_level='Grade 11' AND enrollment_status!='withdrawn' $w GROUP BY pathway_strand,sex ORDER BY pathway_strand");
  if($r) while($row=$r->fetch_assoc()){
    $lb=get_pathway_strand_label('Grade 11',$row['pathway_strand']);
    if(!$lb) $lb=$row['pathway_strand']?:'Unset';
    if($row['sex']==='Male') $d['pathways_m'][$lb]=($d['pathways_m'][$lb]??0)+(int)$row['c'];
    else $d['pathways_f'][$lb]=($d['pathways_f'][$lb]??0)+(int)$row['c'];
  }
  // G12 Strands by gender
  $r=$dc->query("SELECT pathway_strand,sex,COUNT(*) c FROM students WHERE grade_level='Grade 12' AND enrollment_status!='withdrawn' $w GROUP BY pathway_strand,sex ORDER BY pathway_strand");
  if($r) while($row=$r->fetch_assoc()){
    $lb=get_pathway_strand_label('Grade 12',$row['pathway_strand']);
    if(!$lb) $lb=$row['pathway_strand']?:'Unset';
    if($row['sex']==='Male') $d['strands_m'][$lb]=($d['strands_m'][$lb]??0)+(int)$row['c'];
    else $d['strands_f'][$lb]=($d['strands_f'][$lb]??0)+(int)$row['c'];
  }
  // Student types
  $r=$dc->query("SELECT student_type,COUNT(*) c FROM students WHERE enrollment_status!='withdrawn' $w GROUP BY student_type");
  if($r) while($row=$r->fetch_assoc()){
    $t=$row['student_type']??'';
    if(array_key_exists($t,$d['types'])) $d['types'][$t]=(int)$row['c'];
  }
  // Daily by gender
  $yr=date('Y');
  $days=['Jun 1','Jun 2','Jun 3','Jun 4','Jun 5','Jun 6','Late (Jun 7+)'];
  foreach($days as $dl){$d['daily_m'][$dl]=0;$d['daily_f'][$dl]=0;}
  for($i=1;$i<=6;$i++){
    $dt="$yr-06-".str_pad($i,2,'0',STR_PAD_LEFT);
    $r=$dc->query("SELECT sex,COUNT(*) c FROM students WHERE DATE(created_at)='$dt' AND enrollment_status!='withdrawn' $w GROUP BY sex");
    if($r) while($row=$r->fetch_assoc()){
      if($row['sex']==='Male') $d['daily_m']["Jun $i"]=(int)$row['c'];
      else $d['daily_f']["Jun $i"]=(int)$row['c'];
    }
  }
  $r=$dc->query("SELECT sex,COUNT(*) c FROM students WHERE DATE(created_at)>='$yr-06-07' AND MONTH(created_at)=6 AND enrollment_status!='withdrawn' $w GROUP BY sex");
  if($r) while($row=$r->fetch_assoc()){
    if($row['sex']==='Male') $d['daily_m']['Late (Jun 7+)']=(int)$row['c'];
    else $d['daily_f']['Late (Jun 7+)']=(int)$row['c'];
  }
  $dc->close();
}
// Build unified pathway/strand label sets
$pw_labels=array_unique(array_merge(array_keys($d['pathways_m']),array_keys($d['pathways_f'])));
$st_labels=array_unique(array_merge(array_keys($d['strands_m']),array_keys($d['strands_f'])));
// Sort by total desc
usort($pw_labels,function($a,$b)use($d){return(($d['pathways_m'][$b]??0)+($d['pathways_f'][$b]??0))-(($d['pathways_m'][$a]??0)+($d['pathways_f'][$a]??0));});
usort($st_labels,function($a,$b)use($d){return(($d['strands_m'][$b]??0)+($d['strands_f'][$b]??0))-(($d['strands_m'][$a]??0)+($d['strands_f'][$a]??0));});
$pw_m=[];$pw_f=[];foreach($pw_labels as $l){$pw_m[]=$d['pathways_m'][$l]??0;$pw_f[]=$d['pathways_f'][$l]??0;}
$st_m=[];$st_f=[];foreach($st_labels as $l){$st_m[]=$d['strands_m'][$l]??0;$st_f[]=$d['strands_f'][$l]??0;}
$active=$d['g11']+$d['g12'];
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<!-- Welcome Banner -->
<div class="bg-gradient-to-r from-dranhs-dark to-slate-800 rounded-2xl p-6 lg:p-10 mb-8 text-white relative overflow-hidden shadow-lg">
    <div class="absolute right-0 top-0 h-full w-1/2 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
    <div class="relative z-10">
        <h2 class="text-3xl font-heading font-black mb-2">Welcome back, <?php echo htmlspecialchars(ucfirst($username)); ?>!</h2>
        <p class="text-slate-300 max-w-xl">Enrollment Analytics &mdash; <?php echo htmlspecialchars($current_sy?:date('Y').' - '.(date('Y')+1)); ?></p>
        <div class="mt-6 flex gap-3">
            <?php if(in_array('student',$allowed_pages)):?><a href="?page=student" class="bg-dranhs-green hover:bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-bold text-sm transition-colors shadow-lg shadow-emerald-900/20">View Enrollments</a><?php endif;?>
            <?php if(in_array('evaluation',$allowed_pages)):?><a href="?page=evaluation" class="bg-white/10 hover:bg-white/20 text-white px-5 py-2.5 rounded-lg font-bold text-sm transition-colors backdrop-blur-sm">Pending Evaluations</a><?php endif;?>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-100">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Active</p>
        <h3 class="text-2xl font-heading font-black text-dranhs-dark"><?php echo number_format($active);?></h3>
        <p class="text-[10px] text-slate-400 mt-1">G11: <?php echo $d['g11'];?> &bull; G12: <?php echo $d['g12'];?></p>
    </div>
    <div class="bg-amber-50 p-5 rounded-xl shadow-sm border border-amber-200">
        <p class="text-[10px] font-bold text-amber-500 uppercase tracking-widest mb-1">For Evaluation</p>
        <h3 class="text-2xl font-heading font-black text-amber-600"><?php echo $d['for_eval'];?></h3>
    </div>
    <div class="bg-blue-50 p-5 rounded-xl shadow-sm border border-blue-200">
        <p class="text-[10px] font-bold text-blue-500 uppercase tracking-widest mb-1">For Encoding</p>
        <h3 class="text-2xl font-heading font-black text-blue-600"><?php echo $d['for_enc'];?></h3>
    </div>
    <div class="bg-emerald-50 p-5 rounded-xl shadow-sm border border-emerald-200">
        <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest mb-1">Enrolled</p>
        <h3 class="text-2xl font-heading font-black text-emerald-600"><?php echo $d['enrolled'];?></h3>
    </div>
</div>

<!-- Gender + Student Types + Withdrawn -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Gender Distribution</h4>
        <div class="flex items-center justify-center" style="max-height:200px"><canvas id="genderChart"></canvas></div>
        <div class="flex justify-center gap-6 mt-4 text-sm font-bold">
            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-blue-500"></span>Male: <?php echo $d['male'];?></span>
            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-pink-500"></span>Female: <?php echo $d['female'];?></span>
        </div>
        <!-- Withdrawn badge -->
        <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-[10px] font-bold text-red-400 uppercase tracking-widest">Withdrawn</span>
            <span class="text-lg font-heading font-black text-red-600"><?php echo $d['withdrawn'];?></span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 lg:col-span-2">
        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Student Categories</h4>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <?php
            $tc=['Grade 10 DRANHS Student'=>['bg-emerald-50','text-emerald-700','border-emerald-200','G10 DRANHS'],
                 'Old Student (Grade 11 Completer)'=>['bg-blue-50','text-blue-700','border-blue-200','G11 Completer'],
                 'Old Student (Repeater)'=>['bg-orange-50','text-orange-700','border-orange-200','Old Repeater'],
                 'Transferee'=>['bg-violet-50','text-violet-700','border-violet-200','Transferee'],
                 'Balik-Aral(Returnee)'=>['bg-amber-50','text-amber-700','border-amber-200','Returnee'],
                 'Repeater'=>['bg-rose-50','text-rose-700','border-rose-200','Repeater'],
                 'ALS'=>['bg-cyan-50','text-cyan-700','border-cyan-200','ALS']];
            foreach($d['types'] as $t=>$c):$s=$tc[$t]??['bg-slate-50','text-slate-700','border-slate-200',$t];?>
            <div class="<?php echo $s[0].' '.$s[2];?> border rounded-xl p-4">
                <p class="text-[10px] font-bold <?php echo $s[1];?> uppercase tracking-widest mb-1"><?php echo $s[3];?></p>
                <h3 class="text-xl font-heading font-black <?php echo $s[1];?>"><?php echo $c;?></h3>
            </div>
            <?php endforeach;?>
        </div>
    </div>
</div>

<!-- G11 Pathways with Gender -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 mb-8">
    <div class="flex items-center justify-between mb-1">
        <h4 class="text-xs font-bold text-blue-600 uppercase tracking-widest">Grade 11 &mdash; Career Pathways by Gender</h4>
        <div class="flex gap-4 text-[10px] font-bold">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-blue-500"></span>Male</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-pink-500"></span>Female</span>
        </div>
    </div>
    <p class="text-[10px] text-slate-400 mb-4"><?php echo count($pw_labels);?> pathways</p>
    <div style="height:<?php echo max(200,count($pw_labels)*32);?>px;position:relative"><canvas id="pathwayChart"></canvas></div>
</div>

<!-- G12 Strands with Gender -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 mb-8">
    <div class="flex items-center justify-between mb-1">
        <h4 class="text-xs font-bold text-pink-600 uppercase tracking-widest">Grade 12 &mdash; Strands by Gender</h4>
        <div class="flex gap-4 text-[10px] font-bold">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-blue-500"></span>Male</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-pink-500"></span>Female</span>
        </div>
    </div>
    <p class="text-[10px] text-slate-400 mb-4"><?php echo count($st_labels);?> strands</p>
    <div style="height:<?php echo max(180,count($st_labels)*40);?>px;position:relative"><canvas id="strandChart"></canvas></div>
</div>

<!-- Daily Enrollment by Gender -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
        <div>
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Daily Enrollment by Gender</h4>
            <p class="text-[10px] text-slate-400">Enrollment Week: June 1&ndash;6 &bull; Late: June 7+</p>
        </div>
        <div class="flex gap-4 text-[10px] font-bold">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-blue-500"></span>Male</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-pink-500"></span>Female</span>
            <span class="bg-emerald-50 text-dranhs-green px-2 py-1 rounded-full border border-emerald-200">Total: <?php echo array_sum($d['daily_m'])+array_sum($d['daily_f']);?></span>
        </div>
    </div>
    <div style="height:260px;position:relative"><canvas id="dailyChart"></canvas></div>
</div>

<script>
const D=<?php echo json_encode($d,JSON_HEX_TAG);?>;
const pwL=<?php echo json_encode(array_values($pw_labels),JSON_HEX_TAG);?>;
const pwM=<?php echo json_encode($pw_m);?>;
const pwF=<?php echo json_encode($pw_f);?>;
const stL=<?php echo json_encode(array_values($st_labels),JSON_HEX_TAG);?>;
const stM=<?php echo json_encode($st_m);?>;
const stF=<?php echo json_encode($st_f);?>;

new Chart(document.getElementById('genderChart'),{type:'doughnut',data:{labels:['Male','Female'],datasets:[{data:[D.male,D.female],backgroundColor:['#3b82f6','#ec4899'],borderWidth:0,hoverOffset:8}]},options:{responsive:true,maintainAspectRatio:true,cutout:'65%',plugins:{legend:{display:false}}}});

new Chart(document.getElementById('pathwayChart'),{type:'bar',data:{labels:pwL,datasets:[{label:'Male',data:pwM,backgroundColor:'#3b82f6',borderRadius:4,barPercentage:0.7},{label:'Female',data:pwF,backgroundColor:'#ec4899',borderRadius:4,barPercentage:0.7}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,stacked:true,ticks:{stepSize:1}},y:{stacked:true,ticks:{font:{size:10,weight:'bold'}}}}}});

new Chart(document.getElementById('strandChart'),{type:'bar',data:{labels:stL,datasets:[{label:'Male',data:stM,backgroundColor:'#3b82f6',borderRadius:4,barPercentage:0.7},{label:'Female',data:stF,backgroundColor:'#ec4899',borderRadius:4,barPercentage:0.7}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,stacked:true,ticks:{stepSize:1}},y:{stacked:true,ticks:{font:{size:11,weight:'bold'}}}}}});

const dayL=Object.keys(D.daily_m);
const dayM=Object.values(D.daily_m);
const dayF=Object.values(D.daily_f);
new Chart(document.getElementById('dailyChart'),{type:'bar',data:{labels:dayL,datasets:[{label:'Male',data:dayM,backgroundColor:'#3b82f6',borderRadius:6,barPercentage:0.6},{label:'Female',data:dayF,backgroundColor:'#ec4899',borderRadius:6,barPercentage:0.6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,stacked:true,ticks:{stepSize:1}},x:{stacked:true,ticks:{font:{size:11,weight:'bold'}}}}}});
</script>
