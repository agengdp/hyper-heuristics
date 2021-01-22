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

    /**
     * Shift
     * 
     * @var array
     */
    public $shifts = ['P', 'S', 'M', 'L'];


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
        $employeeFile
    ){

        $this->year         = $year;
        $this->month        = $month;
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
                if($value['schedule'] == 'M'){
                    $posisiNonL[] = $y;
                }

                if($value['schedule'] == 'L'){
                    $posisiL[] = $y;
                }
            }

            $filterJadwalNull = array_filter(array_column($employee['schedules'], 'schedule'));
            $libur = array_count_values($filterJadwalNull);

            if(isset($libur['L']) && $libur['L'] <= $jmlLiburSeharusnya){
                $change     = array_rand($posisiNonL);
                $this->cells['data'][$x]['schedules'][$change]['schedule']  = 'L';
            }elseif(isset($libur['L']) && $libur['L'] >= $jmlLiburSeharusnya){
                $change     = array_rand($posisiL);
                $this->cells['data'][$x]['schedules'][$change]['schedule']  = 'M';
            }

        }
    }

    /**
     * Solve program
     * @param  array $cells 
     * @return void
     */
    private function solve($cells){
        
        if($this->solved($cells)){
            return $cells;
        }

        $possibilities  = $this->nextCells($cells);
        $validCells     = $this->keepOnlyValid($possibilities);

        return $this->searchForSolution($validCells);
    }

    /**
     * Populate schedule
     * 
     * @param  array $cells 
     * @return recursion
     */
    private function searchForSolution($cells){

        if(count($cells) < 1){
            return false;
        }

        $first = $cells[array_rand($cells)];
        // $first = array_shift($cells);
        $tryPath = $this->solve($first);

        if($tryPath !== false){
            return $tryPath;
        }

        return $this->searchForSolution($cells);
    }

    /**
     * Cek jadwal apakah sudah populated apa belum
     * 
     * @param  array $cells 
     * @return bool
     */
    private function solved($cells){

        foreach($cells as $values){

            foreach($values as $value){
                if($value['schedule'] === null){
                    return false;
                }
            }

        }

        return true;
    }

    /**
     * Create new cells and add assigned answer
     * @param  array $cells 
     * @return array
     */
    private function nextCells($cells){

        $res = [];
        $firstEmpty = $this->findEmpty($cells);

        if($firstEmpty !== null){

            $x = $firstEmpty[0];
            $y = $firstEmpty[1];

            foreach($this->shifts as $shift){

                $cells[$x][$y]['schedule'] = $shift;
                $res[] = $cells;
            }

        }

        return $res;

    }

    /**
     * Find empty cells
     * @param  array $cells 
     * @return mixed        
     */
    private function findEmpty($cells){

        for($x = 0; $x < count($this->cells); $x++){
            for($y = 0; $y < count($this->employees); $y++){

                if($cells[$x][$y]['schedule'] === null){
                    return [$x, $y];
                }
            }
        }

        return false;
    }

    /**
     * Keep valid answer
     * @param  array $cells
     * @return array
     */
    private function keepOnlyValid($cells){

        $res = [];

        for($x = 0; $x < count($cells); $x++){
            if($this->validCells($cells[$x])){
                $res[] = $cells[$x];
            }
        }

        return $res;
    }

    /**
     * Cek apakah cells valid
     * @param  array $cells 
     * @return boolean
     */
    private function validCells($cells){

        return $this->karuConstraint($cells) &&
               $this->liburTidakBolehGandengConstraint($cells) &&
               $this->shiftTidakBolehGandengTigaKaliConstraint($cells) &&
               $this->shiftTidakBolehDariMalamKePagiConstraint($cells) &&
               $this->shiftHarusMaxTigaPuluhPersenMasuk($cells) && 
               $this->shiftHarusAdaYangJaga($cells) &&
               $this->jumlahLiburSesuaiJumlahMinggu($cells)
               ;

    }

    /**
     * Karu constraint
     * libur tiap hari minggu dan tiap hari Pagi
     * 
     * @param  array $cells
     * @return boolean
     */
    private function karuConstraint($cells){

        foreach($cells as $key => $cell){

            foreach($cell as $employee){

                if($employee['schedule'] == null){
                    continue;
                }
                
                if($employee['employee']['jabatan'] === 'karu'){

                    $date = $key+1;
                    $isSunday = date('w', strtotime($this->month . '/' . $date . '/' . $this->year)) == 0 ? true : false;

                    if($isSunday && $employee['schedule'] !== 'L'){
                        return false;
                    }elseif(!$isSunday && $employee['schedule'] !== 'P'){
                        return false;
                    }
                }

            }
        }

        return true;
    }

    /**
     * Libur tidak boleh gandeng 2x atau lebih
     * 
     * @param  array $cells 
     * @return bool
     */
    private function liburTidakBolehGandengConstraint($cells){

        foreach($cells as $key => $cell){

            foreach($cell as $empKey => $employee){

                if($employee['schedule'] == null || $employee['employee']['jabatan'] === 'karu'){
                    continue;
                }

                if(!isset($cells[$key - 1])){
                    continue;
                }

                if(
                    $cells[$key - 1][$empKey]['schedule'] === 'L' &&
                    $cells[$key - 1][$empKey]['schedule'] === $employee['schedule']
                ){
                    return false;
                }

            }
        }

        return true;
    }

    /**
     * Shift tidak boleh gandeng 3 kali
     * MMM, LLL, SSS
     * 
     * @param  array $cells 
     * @return bool
     */
    private function shiftTidakBolehGandengTigaKaliConstraint($cells){
        foreach($cells as $key => $cell){

            foreach($cell as $empKey => $employee){

                if($employee['schedule'] == null || $employee['employee']['jabatan'] === 'karu'){
                    continue;
                }

                if(!isset($cells[$key - 2])){
                    continue;
                }

                if(
                    $cells[$key - 2][$empKey]['schedule'] === $cells[$key - 1][$empKey]['schedule'] &&
                    $cells[$key - 1][$empKey]['schedule'] === $employee['schedule']
                ){
                    return false;
                }

            }
        }

        return true;
    }

    /**
     * Shift tidak boleh dari malam ke pagi
     * M -> P
     * 
     * @param  array $cells 
     * @return bool
     */
    private function shiftTidakBolehDariMalamKePagiConstraint($cells){
        foreach($cells as $key => $cell){

            foreach($cell as $empKey => $employee){

                if($employee['schedule'] == null || $employee['employee']['jabatan'] === 'karu'){
                    continue;
                }

                if(!isset($cells[$key - 1])){
                    continue;
                }

                if(
                    $cells[$key - 1][$empKey]['schedule'] === 'M' &&
                    $employee['schedule']        === 'P'
                ){
                    return false;
                }

            }
        }

        return true;
    }

    /**
     * Tiap shift harus maksimal 30 masuk
     * 
     * @param  array $cells 
     * @return bool
     */
    private function shiftHarusMaxTigaPuluhPersenMasuk($cells){
        foreach($cells as $key => $cell){

            foreach($cell as $empKey => $employee){

                if($employee['schedule'] == null || $employee['employee']['jabatan'] !== 'anggota'){
                    continue;
                }

                $anggotas = array_filter($cells[$key], function($arr){
                    return $arr['employee']['jabatan'] == 'anggota';
                });

                $jadwalAnggota      = array_column($anggotas, 'schedule');

                // Tiap shift anggota yang masuk bagi rata max 30% dari jumlah
                $filterJadwalNull = array_filter($jadwalAnggota);

                if(!empty($filterJadwalNull)){

                    $hitungJadwalYgSama = array_count_values($filterJadwalNull);

                    if(isset($hitungJadwalYgSama[$employee['schedule']])){
                        if($hitungJadwalYgSama[$employee['schedule']] > count($anggotas) * 30/100){
                            return false;
                        }                        
                    }

                }

            }
        }

        return true;
    }

    /**
     * Shift harus ada yang jaga
     *
     * @param array $cells
     * @return void
     */
    private function shiftHarusAdaYangJaga($cells){
        foreach($cells as $key => $cell){

            foreach($cell as $empKey => $employee){

                if($employee['schedule'] == null || $employee['employee']['jabatan'] !== 'senior'){
                    continue;
                }

                $anggotas = array_filter($cells[$key], function($arr){
                    return $arr['employee']['jabatan'] == 'senior';
                });

                $jadwalAnggota      = array_column($anggotas, 'schedule');

                // Tiap shift anggota yang masuk bagi rata max 30% dari jumlah
                $filterJadwalNull = array_filter($jadwalAnggota);

                if(!empty($filterJadwalNull)){

                    $hitungJadwalYgSama = array_count_values($filterJadwalNull);

                    if(isset($hitungJadwalYgSama[$employee['schedule']])){
                        if($hitungJadwalYgSama[$employee['schedule']] > 2){ // entahlah @todo Kalo dibawah 2 not working
                            return false;
                        }                        
                    }

                }

            }
        }

        return true;
    }

    /**
     * Jumlah libur sesuai dengan jumlah hari minggu
     *
     * @param string $cells
     * @return void
     */
    private function jumlahLiburSesuaiJumlahMinggu($cells){

        foreach($cells as $tgl => $employees){

            foreach($employees as $empKey => $emp){
                if($emp['employee']['jabatan'] === 'karu'){
                    continue;
                }

                $empSchedule = [];
                foreach($cells as $k => $v){
                    if($v[$empKey]['schedule'] !== null){
                        $empSchedule[] = $v[$empKey]['schedule'];
                    }
                }

                $schedule = array_count_values($empSchedule);
                if(isset($schedule['L']) && $schedule['L'] > $this->countSunday()){
                    var_dump($cells);
                    die();
                    return false;
                }
            }
        }

        return true;

        for($emp = 0; $emp < count($cells[0]) - 1; $emp++){

            $libur = 0;
            for($tgl = 0; $tgl < count($cells) - 1; $tgl++){
                if($cells[$tgl][$emp]['employee']['jabatan'] === 'karu'){
                    continue;
                }

                if($cells[$tgl][$emp]['schedule'] === 'L'){
                    $libur++;
                }
            }
            // $libur++;
            // var_dump($libur);

            if($libur != $this->countSunday()){
                return false;
            }
        }

        return true;


        // foreach($cell as $empKey => $employee){

        //     if($employee['schedule'] == null || $employee['employee']['jabatan'] !== 'senior'){
        //         continue;
        //     }

        //     $anggotas = array_filter($cells[$key], function($arr){
        //         return $arr['employee']['jabatan'] == 'senior';
        //     });

        //     $jadwalAnggota      = array_column($anggotas, 'schedule');

        //     // Tiap shift anggota yang masuk bagi rata max 30% dari jumlah
        //     $filterJadwalNull = array_filter($jadwalAnggota);

        //     if(!empty($filterJadwalNull)){

        //         $hitungJadwalYgSama = array_count_values($filterJadwalNull);

        //         if(isset($hitungJadwalYgSama[$employee['schedule']])){
        //             if($hitungJadwalYgSama[$employee['schedule']] > 2){ // entahlah @todo Kalo dibawah 2 not working
        //                 return false;
        //             }                        
        //         }

        //     }

        // }
        // return true;
    }


    /**
     * Pilih jawaban
     * @param  array $cells  seluruh scells
     * @param  int $column kolom (dalam hal ini tanggal)
     * @param  int $row    baris (dalam hal ini key dari employee)
     * @return string
     */
    private function selectAnswer($cells, $column, $row){

        $shift = ['P', 'M', 'S', 'L'];

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
        }


        // Global constraint

        // Unset Shift Pagi, biar gabisa abis masuk malam terus masuk pagi
        if(isset($cells[$column - 1][$row]['schedule']) && $cells[$column - 1][$row]['schedule'] !== 'S'){
            unset($shift[0]);
        }

        // Pattern MML



        // 6X masuk 1x libur
        // Jika 6 hari sebelumnya libur, maka hari ini libur
        // if(isset($cells[$column - 7][$row]['schedule']) && $cells[$column - 7][$row]['schedule'] === 'L'){
            
        //     $day = date('w', strtotime($this->month . '/' . $date . '/' . $this->year));
        
        //     return [
        //         'schedule'      => $shift[3],
        //     ];
        // }

        // Libur tidak boleh lebih dari jumlah hari minggu
        $sunday =  $this->countSunday();
        $libur = 0;
        for($i = 0; $i < count($cells); $i++) {
            if($cells[$i][$row]['schedule'] == 'L'){
                $libur +=1;
            }           
        }
        if($libur >= $sunday){
            unset($shift[3]);
        }

        // Gaboleh libur gandeng dalam jangka waktu 6 hari
        for($i = 0; $i < 7; $i++){
            if(isset($cells[$column - $i][$row]['schedule']) && $cells[$column - $i][$row]['schedule'] === 'L'){
                unset($shift[3]);
            }
        }

        // Pattern tidak boleh 3x
        $prev1      = '';
        $prev2      = '';
        $prevValue  = '';
        if(isset($cells[$column - 1][$row]['schedule'])){
            $prev1 = $cells[$column - 1][$row]['schedule'];
        }

        if(isset($cells[$column - 2][$row]['schedule'])){
            $prev2 = $cells[$column - 2][$row]['schedule'];
        }

        if(!empty($prev1) && !empty($prev2) && $prev1 == $prev2){
            $prevValue = $prev1;
        }

        if(!empty($prevValue)){
            $findValueIndex = array_search($prevValue, $shift);
            unset($shift[$findValueIndex]);
        }

        // Reset index shift after unset
        $shift = array_values($shift);

        // Mirroring shift
        $answer = $shift;

        // Senior Constraint
        if($cells[$column][$row]['employee']['jabatan'] === 'senior'){

            // Tiap shift harus ada yg masuk
            // Filter senior only
            $seniors = array_filter($cells[$column], function($arr){
                return $arr['employee']['jabatan'] == 'senior';
            });

            $jadwalSenior = array_column($seniors, 'schedule');
            $diff = array_diff($shift, array_unique($jadwalSenior));

            if(!empty($diff)){
                $answer = $diff;
            }
        }

        // Anggota Constraint
        if($cells[$column][$row]['employee']['jabatan'] === 'anggota'){

            $unfilterAnswer = $answer;

            // Tiap shift harus ada yg masuk
            // Filter anggota only
            $anggotas = array_filter($cells[$column], function($arr){
                return $arr['employee']['jabatan'] == 'anggota';
            });

            $jadwalAnggota      = array_column($anggotas, 'schedule');

            // Tiap shift anggota yang masuk bagi rata max 30% dari jumlah
            $filterJadwalNull = array_filter($jadwalAnggota);
            
            $persentase = count($anggotas) * 30/100;

            if(!empty($filterJadwalNull)){
                $hitungJadwalYgSama = array_count_values($filterJadwalNull);
                unset($hitungJadwalYgSama['L']);
                
                $filterAnswer = array_filter($hitungJadwalYgSama, function($v) use($persentase){
                    return $v <= $persentase;
                });

                $remapAnswer = [];
                foreach($unfilterShift as $k => $v){
                    if(( $key = array_search($v, $hitungJadwalYgSama)) !== false){
                        if($hitungJadwalYgSama[$key] < $persentase){
                            $remapAnswer[$k] = $v;
                        }
                    }
                }

                $answer = $remapAnswer;

                // if(empty($filterAnswer)){
                //     $answer = $unfilterAnswer;
                // }else{
                //     $remapAnswer = [];
                //     foreach($filterAnswer as $jadwal => $jumlah){
                //         if(( $key = array_search($jadwal, $answer)) !== false){
                //             $remapAnswer[] = $answer;
                //         }
                //     }
                //     $answer = $remapAnswer;
    
                // }
    
            }

            if(empty($answer)){
                echo $row . ' - ' . $column;
                echo '<hr />';
                echo 'unfilter answer: ';
                var_dump($unfilterAnswer);
                echo '<hr/>';
                echo 'hitungjadwal: ';
                var_dump($hitungJadwalYgSama);
                echo '<hr/>';
                echo 'answer: ';
                var_dump($answer);
                echo '<hr/>';

                $answer = $unfilterAnswer;
            }

            // Tiap shift harus ada yg masuk
            $diff = array_diff($shift, array_unique($jadwalAnggota));
                
            if(!empty($diff)){
                $answer = $diff;
            }
    
        }

        $result = $shift[array_rand($answer)];

        return [
            'schedule'      => $result
        ];

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
     * @return void
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

    /**
     * Move
     *
     * @return void
     */
    private function move(){

        // Random find Employee excluxe 0 (Karu)
        $employeeIndex = 1;
        while( in_array( ($employeeIndex = random_int(1, count($this->cells['data']) - 1)), array(0)));

        $findLposition = array_filter($this->cells['data'][$employeeIndex]['schedules'], function($arr){
            return $arr = $arr['schedule'] == 'L';
        });

        $dateIndex = array_rand($findLposition);

        $afterValue = '';
        if(isset($this->cells['data'][$employeeIndex][$dateIndex + 1])){
            $afterValue = $this->cells['data'][$employeeIndex]['schedules'][$dateIndex + 1]['schedule'];
        }

        if(!empty($afterValue)){
            $this->cells['data'][$employeeIndex]['schedules'][$dateIndex]['schedule'] = $afterValue;
            $this->cells['data'][$employeeIndex]['schedules'][$dateIndex + 1]['schedule'] = 'L';
        }else{
            $shift = ['P', 'S', 'M'];
            $this->cells['data'][$employeeIndex]['schedules'][$dateIndex]['schedule'] = $this->cells['data'][$employeeIndex]['schedules'][0]['schedule'];
            $this->cells['data'][$employeeIndex]['schedules'][0]['schedule'] = 'L';
        }
    }

     /**
     * Swap
     * @return void
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
     * @param int $iteration
     * @return void
     */
    public function reinforcementLearning($iteration){
        $lowLevels = ['move', 'swap'];

        $scores = [
            'move'  => 10,
            'swap'  => 10
        ];

        $currentJFI = $bestJFI = $this->countJFI();

        for($i = 0; $i < $iteration; $i++){

            // hentikan jika move / swap sudah sampek skor 20
            if(
                $scores['move'] === 20 || $scores['swap'] === 20 ||
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
     * @param int $iteration
     * @return void
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
     * @return void
     */
    private function f_rand($min=0,$max=1,$mul=1000000){
        if ($min>$max) return false;
        return mt_rand($min*$mul,$max*$mul)/$mul;
    }


}

// $schedule = new Generate(2020, 11, 'data/asoka.txt');
// $schedule->initalize();