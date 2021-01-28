<?php 

class Generate{

    /**
     * Year
     *
     * @var int
     */
    private $year;

    /**
     * month
     *
     * @var int
     */
    private $month;

    /**
     * Karyawan list
     *
     * @var array
     */
    private $employees;

    /**
     * Block 
     *
     * @var array
     */
    public $cells;

    
    private $unitLayanan;

    private $constrainUnit;

    /**
     * Contructor
     *
     * @param string $year
     * @param string $month
     * @param array $employeeFile
     */
    public function __construct(
        $year, 
        $month, 
        $employeeFile,
        $unitLayanan
    ){

        $this->year         = $year;
        $this->month        = $month;
        $this->unitLayanan  = $unitLayanan;
        $this->employees    = $this->formatEmployee($employeeFile);
        $this->cells        = $this->generateCells();

        return $this;

    }

    /**
     * Format employee as desired
     *
     * @return void
     */
    private function formatEmployee($employee){
        $employees = fopen($employee, "r");

        if(!$employees){
            die("Cannot find employee file");
        }
        $emp = [];

        while(($line = fgets($employees)) !== false) {
            $mm = explode(';', trim($line));
            $emp[] = [
                'nama'          => $mm[0],
                'jabatan'       => strtolower($mm[1]),
                'spesialisasi'  => $mm[2]
            ];
        }
        fclose($employees);

        return $this->employees = $emp;        
    }

    /**
     * Generate cells
     *
     * @return void
     */
    private function generateCells(){
        $dayCount = date('t', strtotime($this->month . '/1/' . $this->year));
        
        $cells = [];

        for($x = 0; $x < $dayCount; $x++){
            
            for($y = 0; $y < count($this->employees); $y++){
                $cells[$x][$y] = [
                    'employee'      => $this->employees[$y],
                    'schedule'      => null,
                    'bobot'           => 0
                ];
            }
        }

        return $this->cells = $cells;

    }
    /**
     * Initialize script
     * 
     * @return self
     */
    public function initalize(){

        $this->cells = $this->solve($this->cells);
        $this->mapByEmployee();
        $this->validateLibur();
        $this->countJFI();

        return $this;

    }
    /**
     * Validate libur sudah sesuai tiap rows
     *
     * @return void
     */
    private function validateLibur(){
        $jmlLiburSeharusnya = $this->countSunday();
        foreach ($this->cells['data'] as $x => $employee){
            if($employee['jabatan'] == 'karu'){
                continue;
            }
            $posisiNonL = [];
            $posisiL    = [];
            foreach($employee['schedules'] as $y => $value){
                if(isset($employee['schedules'][$y]['schedule']) && $employee['schedules'][$y]['schedule'] !== 'L'){
                    $posisiNonL[] = $y;
                }else{
                    $posisiL[] = $y;
                }
            }
            $filterJadwalNull = array_filter(array_column($employee['schedules'], 'schedule'));
            $libur = array_count_values($filterJadwalNull);
            if(isset($libur['L']) && $libur['L'] > $jmlLiburSeharusnya){                    
                $change     = array_rand($posisiL);
                if ($change>0){
                    if ($this->cells['data'][$x]['schedules'][$posisiL[$change-1]]['schedule']=="M"){
                        $this->cells['data'][$x]['schedules'][$posisiL[$change]]['schedule']  = 'S';
                    }else{
                        $this->cells['data'][$x]['schedules'][$posisiL[$change]]['schedule']  = 'P';
                    }
                }else{
                    $this->cells['data'][$x]['schedules'][$posisiL[$change]]['schedule']  = 'P';
                }
            }
            if(isset($libur['L']) && $libur['L'] < $jmlLiburSeharusnya){
                //perlu check rule non libur XXX
                //$change     = array_rand($posisiNonL);
                //$this->cells['data'][$x]['schedules'][$posisiNonL[$change]]['schedule']  = 'L';
            }
        }
    }
    /**
     * Solve program
     * @param  array $cells 
     * @return void
     */
    private function solve($cells){
        
        if($this->schedulePopulated($cells)){
            return $cells;
        }

        return $this->populateSchedule($cells);
    }

    /**
     * Cek jadwal apakah sudah populated apa belum
     * 
     * @param  array $cells 
     * @return bool
     */
    private function schedulePopulated($cells){

        foreach($cells as $values){

            foreach($values as $value){
                if($value['schedule'] === null){
                    return false;
                }
            }

        }

        return true;
    }

    private function getConstrainUnit(){
        if ($this->unitLayanan=="asoka"){
            $contUnit = array('P' => 4,'S' => 3,'M' => 3);
        }
        return $contUnit;
    }
    /**
     * Populate schedule
     * 
     * @param  array $cells 
     * @return recursion
     */
    private function populateSchedule($cells){
        $this->constrainUnit=$this->getConstrainUnit();
        foreach($cells as $column => $values){

            foreach($values as $row => $value){
                if($value['schedule'] !== null){
                    continue;
                }				
                $answer = $this->selectAnswer($cells, $column, $row);

                if($answer !== false){
                    $cells[$column][$row]['schedule']   = $answer['schedule'];
                }
            }

        }

        return $this->solve($cells);
    }

    /**
     * Pilih jawaban
     * @param  array $cells  seluruh scells
     * @param  int $column kolom (dalam hal ini tanggal)
     * @param  int $row    baris (dalam hal ini key dari employee)
     * @return string
     */
	
	private function selectAnswer($cells, $column, $row){
        $shift = ['P', 'S', 'M', 'L'];       
        $date = $column+1;

        // Cek apakah ini hari minggu
        $isSunday = date('w', strtotime($this->month . '/' . $date . '/' . $this->year)) == 0 ? true : false;
        // constraint Karu
        if($cells[$column][$row]['employee']['jabatan'] === 'karu'){
            if($isSunday){
                return [
                    'schedule'      => $shift[3]
                ];
            }
            return [
                'schedule'      => $shift[0]
            ];
        }else{
            $availShift = $this->availShiftFromConstrain($cells, $column, $row,$shift);
            if (count($availShift)==0) {
                if (isset($this->cells['data'][$row]['schedules'][$column-1]['schedule']) && $this->cells['data'][$row]['schedules'][$column-1]['schedule']=="M"){
                    $result = $shift[1];
                }else{
                    $availShift = ['P', 'S'];  
                    $result=$shift[array_rand($availShift)];
                }                
            }elseif(isset($availShift[0])&&$availShift[0]=="X"){
                $result = $shift[3];
            }else{
                $result = $shift[array_rand($availShift)];
            }
            return [
                'schedule'      => $result
            ];		
        }		
    }
    private function getTotalShift($cells, $column,$jabatan){
        if ($jabatan=="All"){
            $rules = array_filter($cells[$column], function($arr){
                return $arr['employee']['jabatan'] != "karu";
            });
        }else{
            $rules = array_filter($cells[$column], function($arr){
                return $arr['employee']['jabatan'] == "senior";
            });
        }
		$jadwalRule = array_column($rules, 'schedule');
		$filterJadwalNull = array_filter($jadwalRule);
		
		$totShift=array("P"=>0,"S"=>0,"M"=>0,"L"=>0);		
		if(!empty($filterJadwalNull)){
            $hitungJadwalYgSama = array_count_values($filterJadwalNull);
			$totShift['P'] = isset($hitungJadwalYgSama['P'])?$hitungJadwalYgSama['P']:0;
			$totShift['S'] = isset($hitungJadwalYgSama['S'])?$hitungJadwalYgSama['S']:0;
            $totShift['M'] = isset($hitungJadwalYgSama['M'])?$hitungJadwalYgSama['M']:0;
            $totShift['L'] = isset($hitungJadwalYgSama['L'])?$hitungJadwalYgSama['L']:0;
        }
        return $totShift;
    }

    private function haveDataLibur($cells, $column, $row){
        $arrWeek = $this->arrSunday();
        $jmlMinggu = count($arrWeek);
        $dayCount = date('t', strtotime($this->month . '/1/' . $this->year));
        for ($i=0;$i<count($arrWeek);$i++){
            if ($column+1<$arrWeek[0]){
                for($j=0;$j<$arrWeek[0];$j++){
                    if(isset($cells[$j][$row]['schedule']) && $cells[$j][$row]['schedule'] === 'L'){
                        return 1;
                    }
                }
                if (isset($arrWeek[0])&&$arrWeek[0]==$column+1){
                    return 2;
                }
            }elseif($arrWeek[$i]<=$column && (isset($arrWeek[$i+1])&&$arrWeek[$i+1]>$column)){
                for($j=$arrWeek[$i];$j<=$arrWeek[$i+1];$j++){
                    if(isset($cells[$j][$row]['schedule']) && $cells[$j][$row]['schedule'] === 'L'){
                        return 1;
                    }
                }
                if (isset($arrWeek[$i+1])&&$arrWeek[$i+1]==$column+1){
                    return 2;
                }
            }elseif ($column+2>$arrWeek[$jmlMinggu-1]){
                for($j=$arrWeek[$jmlMinggu-1];$j<$dayCount;$j++){
                    if(isset($cells[$j][$row]['schedule']) && $cells[$j][$row]['schedule'] === 'L'){
                        return 1;
                    }
                }
            }
        }
        return 0;
    }
	private function availShiftFromConstrain($cells, $column, $row,$shift){
        $availShift = $shift;
		$rule = $cells[$column][$row]['employee']['jabatan'];
		if ($rule=='senior'){
            $seniors = array_filter($cells[$column], function($arr){
                return $arr['employee']['jabatan'] == "senior";
            });
            $maxLibur = count($seniors)-3;
            $totShiftSenior = $this->getTotalShift($cells, $column, $rule);
            if($totShiftSenior['L']==$maxLibur){unset($availShift[3]);};
            if($totShiftSenior['P']>0){unset($availShift[0]);};
            if($totShiftSenior['S']>0){unset($availShift[1]);};
            if($totShiftSenior['M']>0){unset($availShift[2]);};
        }else{
            $alls = array_filter($cells[$column], function($arr){
                return $arr['employee']['jabatan'] != "karu";
            });
            $maxLibur = count($alls)-($this->constrainUnit['P']+$this->constrainUnit['S']+$this->constrainUnit['M']);
            $totShiftSenior = $this->getTotalShift($cells, $column, "All");
            if($totShiftSenior['L']==$maxLibur){unset($availShift[3]);};
            if($totShiftSenior['P']>=$this->constrainUnit['P']){unset($availShift[0]);};
            if($totShiftSenior['S']>=$this->constrainUnit['S']){unset($availShift[1]);};
            if($totShiftSenior['M']>=$this->constrainUnit['M']){unset($availShift[2]);};
        }
        $lbr = $this->haveDataLibur($cells, $column, $row);
        if ((isset($cells[$column-1][$row]['schedule']) && $cells[$column-1][$row]['schedule'] === 'M')){
            unset($availShift[2]);
            unset($availShift[0]);
        }
        if ($lbr ==1){
            unset($availShift[3]);
        }elseif ($lbr ==2){
            $availShift=["X"];
        }
        return $availShift;
	}
    /**
     * Map cells by Employee
     *
     * @return void
     */
    private function mapByEmployee(){
        $cells      = [];
        $schedule   = [];

        foreach($this->cells as $y => $columns){
            foreach($columns as $x => $row){
                $cells[$x]              = $row['employee'];
                foreach($this->cells as $k => $r){
                    $cells[$x]['schedules'][] = [
                        'schedule'      => $r[$x]['schedule']
                    ];
                }
            }
        }

        $this->cells['data'] = $cells;
    }

    /**
     * Hitung JFI
     *
     * @return float|int
     */
    private function countJFI(){

        $cells = [];
        $jmlEmpKuadrat = 0;
        $jmlSumEmp = 0;

        foreach($this->cells['data'] as $x => $row){

            $bobot = 0;
            foreach($row['schedules'] as $key => $schedule){
                if($schedule['schedule'] == 'L'){
                    $date = $key +1;
                    $day = date('w', strtotime($this->month . '/' . $date . '/' . $this->year));
                    if($day == 0){
                        $bobot += 4;
                    }elseif($day == 6){
                        $bobot += 2;
                    }else{
                        $bobot += 1;
                    }
                }
            }

            $totalBobot = $bobot;
            $cells[$x]  = $row;
            $cells[$x]['bobot'] = $totalBobot;

            $empKuadrat = pow($totalBobot, 2);
            $jmlEmpKuadrat += $empKuadrat;
            $jmlSumEmp += $totalBobot;

            $cells[$x]['jfi'] = $totalBobot;
        }

        $jmlSumEmpKuadrat = pow($jmlSumEmp, 2);
        $jfi = $jmlSumEmpKuadrat / (count($this->employees) * $jmlEmpKuadrat);

        $this->cells['jfi'] = $jfi;
        return $jfi;
    }

    /**
     * Hitung jumlah minggu
     *
     * @return void
     */
    private function countSunday(){
        $totaldays = date('t', strtotime($this->year.'-'.$this->month.'-01'));
        $week = 0;
        for($i = 1; $i <= $totaldays; $i++){
            $day = date('w', strtotime($this->year.'-'.$this->month.'-'. $i));
            if($day == 0){
                $week += 1;
            }
        }
        return $week;
    }
    private function arrSunday(){
        $totaldays = date('t', strtotime($this->year.'-'.$this->month.'-01'));
        $arrWeek=[];
        for($i = 1; $i <= $totaldays; $i++){
            $day = date('w', strtotime($this->year.'-'.$this->month.'-'. $i));
            if($day == 0){
                array_push($arrWeek,$i);
            }
        }
        return $arrWeek;
    }

    /**
     * Move
     *
     * @throws Exception
     */
    private function move(){

        // Random find Employee excluxe 0 (Karu)
        $employeeIndex = 1;
        while( in_array( ($employeeIndex = random_int(1, count($this->cells['data']) - 1)), array(0)));

        $findLposition = array_filter($this->cells['data'][$employeeIndex]['schedules'], function($arr){
            return $arr = $arr['schedule'] == 'L';
        });

        // cari posisi minggu
        $minggu = array_filter($this->cells['data'][0]['schedules'], function($arr){
            return $arr['schedule'] == 'L';
        });
        $posisiMinggu = array_keys($minggu);
        
        $dateIndex = array_rand($findLposition);
        $dateMinggu = array_rand($minggu);

        $afterValue = '';
        if(isset($this->cells['data'][$employeeIndex]['schedules'][$dateMinggu])){
            $afterValue = $this->cells['data'][$employeeIndex]['schedules'][$dateMinggu]['schedule'];
        }

        if(!empty($afterValue)){
            $this->cells['data'][$employeeIndex]['schedules'][$dateIndex]['schedule'] = $afterValue;
            $this->cells['data'][$employeeIndex]['schedules'][$dateMinggu]['schedule'] = 'L';
        }else{
            $shift = ['P', 'S', 'M'];
            $this->cells['data'][$employeeIndex]['schedules'][$dateIndex]['schedule'] = $this->cells['data'][$employeeIndex]['schedules'][0]['schedule'];
            $this->cells['data'][$employeeIndex]['schedules'][0]['schedule'] = 'L';
        }
    }

    /**
     * Swap
     *
     * @throws Exception
     */
    private function swap(){

        // Random find Employee excluxe 0 (Karu)
        // $employeeIndex = array_rand($this->cells['data']);
        $employeeIndex = 0;
        while( in_array( ($employeeIndex = random_int(1, count($this->cells['data']) - 1)), array(0)));

        // Random find employee exclude current
        $otherEmployeeIndex = 1;
        while( in_array( ($otherEmployeeIndex = random_int(1, count($this->cells['data']) - 1)), array($employeeIndex)));

        $findCurrentLposition = array_filter($this->cells['data'][$employeeIndex]['schedules'], function($arr){
            return $arr = $arr['schedule'] == 'L';
        });

        $findOtherLposition = array_filter($this->cells['data'][$otherEmployeeIndex]['schedules'], function($arr){
            return $arr = $arr['schedule'] == 'L';
        });

        $currentLposition = array_rand($findCurrentLposition);
        $otherLposition = array_rand($findOtherLposition);

        $currentPositionForSwapValue    = $this->cells['data'][$employeeIndex]['schedules'][$otherLposition]['schedule'];
        $otherPositionForSwapValue      = $this->cells['data'][$otherEmployeeIndex]['schedules'][$currentLposition]['schedule'];

        if($currentPositionForSwapValue !== 'L' && $otherPositionForSwapValue !== 'L'){
            $this->cells['data'][$otherEmployeeIndex]['schedules'][$currentLposition]['schedule']   = 'L';
            $this->cells['data'][$employeeIndex]['schedules'][$currentLposition]['schedule']        = $otherPositionForSwapValue;

            $this->cells['data'][$employeeIndex]['schedules'][$otherLposition]['schedule']          = 'L';
            $this->cells['data'][$otherEmployeeIndex]['schedules'][$otherLposition]['schedule']     = $otherPositionForSwapValue;
        }else{
            $this->swap();
        }

    }

    /**
     * Reinforcement Learning
     *
     * @param $iteration
     * @return array
     * @throws Exception
     */
    public function reinforcementLearning($iteration){
        $lowLevels = ['move', 'swap'];

        $scores = [
            'move'  => 1000,
            'swap'  => 1000
        ];

        $currentJFI = $bestJFI = $this->countJFI();

        for($i = 0; $i < $iteration; $i++){

            // hentikan jika move / swap sudah sampek skor 20
            if(
                $scores['move'] === 400 || $scores['swap'] === 400 ||
                $scores['move'] === 0 || $scores['swap'] === 0
            ){

                return $this->cells;
                break;
            }

            $currentCells = $this->cells;

            echo $scores['move'] . ' || '. $scores['swap'] . ' <br/>' ;

            // Jika nilai move & swap sama maka random
            if($scores['move'] == $scores['swap']){
                $method = array_rand($lowLevels);
                $call = $this->{$lowLevels[$method]}();
                $currentJFI = $this->countJFI();

                if($currentJFI > $bestJFI){
                    if($method == 0){
                        $method = 'move';
                        $scores['move']++;
                    }else{
                        $method = 'swap';
                        $scores['swap']++;
                    }
                }else{
                    if($method == 1){
                        $method = 'move';
                        $scores['move']--;
                    }else{
                        $method = 'swap';
                        $scores['swap']--;
                    }
                }

            }elseif( $scores['move'] < $scores['swap']){
                $this->swap();
                $method = 'swap';
                $currentJFI = $this->countJFI();

                if($currentJFI > $bestJFI){
                    $scores['swap']++;
                }else{
                    $scores['swap']--;
                }

            }else{
                $this->move();
                $method = 'move';
                $currentJFI = $this->countJFI();

                if($currentJFI > $bestJFI){
                    $scores['move']++;
                }else{
                    $scores['move']--;
                }
            }

            echo $currentJFI . ' || ' . $bestJFI . ' || ' . $method .'<br/>';

            if($currentJFI > $bestJFI){
                $bestJFI = $currentJFI;
            }else{
                $this->cells = $currentCells;
            }

        }
    }

    /**
     * Hill Climbing
     *
     * @param $iteration
     * @throws Exception
     */
    public function hillClimbing($iteration){
        $currentJFI = $bestJFI = $this->countJFI();

        for($i = 0; $i < $iteration; $i++){

            $currentCells = $this->cells;

            if($this->f_rand(0, 1) < 0.5){
                $method = 'move';
                $this->move();
                $currentJFI = $this->countJFI();

            }else{
                $method = 'swap';
                $this->swap();
                $currentJFI = $this->countJFI();
            }

            if($currentJFI > $bestJFI){
                $bestJFI = $currentJFI;
            }else{
                $this->cells = $currentCells;
            }

            echo $currentJFI . ' || ' . $bestJFI . ' || ' . $method .' || '. $i .'<br/>';


        }

    }

    /**
     * Generate double precision random
     *
     * @param integer $min
     * @param integer $max
     * @param integer $mul
     * @return int
     */
    private function f_rand($min=0,$max=1,$mul=1000000){
        if ($min>$max) return false;
        return mt_rand($min*$mul,$max*$mul)/$mul;
    }


}
