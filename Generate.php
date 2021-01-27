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

    private $countSunday;

    /**
     * Contructor
     *
     * @param string $year
     * @param string $month
     * @param string $employeeFile
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
        $this->countSunday  = $this->countSunday();

        return $this;

    }

    /**
     * Format employee as desired
     *
     * @return array
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
     * @return array
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

        $this->mapByEmployee($this->cells);
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
     * @return array
     */
    private function solve($cells){

        if($this->solved($cells)){
            return $cells;
        }

        $possibilities  = $this->nextCells($cells);
        $validCells     = $this->keepOnlyValid($possibilities);

        if(empty($validCells)){
            return $this->rollback($cells);
        }

        return $this->searchForSolution($validCells);
    }

    /**
     * Populate schedule
     *
     * @param  array $cells
     * @return mixed
     */
    private function searchForSolution($cells){

        if(count($cells) < 1){
            echo 'Tidak ada solusi';
            die();
        }

//        $first = array_shift($cells);
        $first = $cells[array_rand($cells)];
        $tryPath = $this->solve($first);

        if($tryPath !== false){
            return $tryPath;
        }

        return $this->searchForSolution($cells);
    }

    /**
     * Rollback cells jika tidak ketemu solusi
     *
     * @param $cells
     * @return array|mixed
     */
    private function rollback($cells){
        $firstEmpty = $this->findEmpty($cells);

        if($firstEmpty !== null){
            $x = $firstEmpty[0];
            $y = $firstEmpty[1];

            var_dump('enter rollback');
            echo '<br/>';

            if($x === 1 && $y === 0){
                echo 'Karu di ignore';
                die();
            }

            $locSebelumEmpty = [$x, $y - 1];
            if($y === 0){
                $locSebelumEmpty = [$x - 1, count($cells[0]) - 1];
            }

            var_dump($firstEmpty);
            echo '<br/>';
            var_dump($y);
            echo '<br/>';
            var_dump($locSebelumEmpty);
            echo '<hr/>';
            var_dump(count($cells));
            echo '<hr/>';

            $previousValue = $cells[$locSebelumEmpty[0]][$locSebelumEmpty[1]]['schedule'];
            $cells[$locSebelumEmpty[0]][$locSebelumEmpty[1]]['schedule'] = null;

            $possibilities = $this->nextCells($cells);
            $validCells = $this->keepOnlyValid($possibilities);

            if(empty($validCells)){
                return $this->rollback($cells);
            }
            $validatedCells = $this->validateRollback($validCells, $previousValue, $locSebelumEmpty);

            if(empty($validatedCells)){
                return $this->rollback($cells);
            }

            return $this->searchForSolution($validatedCells);
        }
    }

    /**
     * Rolled back list,
     * jadi jawaban yang sudah di rollback ditaruh disini
     * Jika nanti muncul solusi ditempat tersebut tapi sudah dicoba
     * maka akan naik 1
     *
     * @var array
     */
    private $rolledBack = [];

    /**
     * Validasi rollback
     *
     * @param $cells
     * @param $prevValue
     * @param $locSebelumEmpty
     * @return array
     */
    private function validateRollback($cells, $prevValue, $locSebelumEmpty){
        $res = [];

        foreach($cells as $cell){
//            var_dump($prevValue);
//            echo '<br/>';
//            var_dump($cell[$locSebelumEmpty[0]][$locSebelumEmpty[1]]['schedule']);
//            echo '<br/>';

            if(isset($this->rolledBack[$locSebelumEmpty[0]][$locSebelumEmpty[1]]) && in_array($prevValue, $this->rolledBack[$locSebelumEmpty[0]][$locSebelumEmpty[1]])){
//                var_dump($this->rolledBack[$locSebelumEmpty[0]][$locSebelumEmpty[1]]);
//                echo '<hr/>';
                continue;
            }

//            echo '<hr/>';
            if(
                $cell[$locSebelumEmpty[0]][$locSebelumEmpty[1]]['schedule'] !== $prevValue
            ){
                $this->rolledBack[$locSebelumEmpty[0]][$locSebelumEmpty[1]][] = $prevValue;
                $res[] = $cell;
            }
        }

        return $res;
    }

    /**
     * Cek jadwal apakah sudah populated apa belum
     *
     * @param array $cells
     * @return boolean
     */
    private function solved(array $cells){

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
     * @param array $cells
     * @return array
     */
    private function nextCells(array $cells){

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
     * @param array $cells
     * @return mixed
     */
    private function findEmpty(array $cells){

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
     *
     * @param $cells
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

        return $this->rowsGood($cells) && $this->columnsGood($cells);

    }

    /**
     * Rows constraint
     *
     * @param array $cells
     * @return boolean
     */
    private function rowsGood($cells){

        foreach($cells as $tgl => $employees){

            foreach($employees as $empKey => $employee){
                if(
                    $this->liburTidakBolehGandengConstraint($cells, $tgl, $empKey, $employee) &&
//                    $this->formatMML($tgl, $empKey, $employee) &&
                    $this->jumlahLiburSesuaiJumlahMinggu($cells, $tgl, $empKey, $employee) &&
                    $this->shiftTidakBolehGandengTigaKaliConstraint($cells, $tgl, $empKey, $employee) &&
                    $this->shiftTidakBolehDariMalamKePagiConstraint($cells, $tgl, $empKey, $employee) &&
                    $this->shiftTidakBolehDariMalamMalam($cells, $tgl, $empKey, $employee) &&
                    $this->karuConstraint($tgl, $employee)
                ){

                }else{
                    return false;
                }

            }

        }

        return true;
    }

    /**
     * Columns constraint
     *
     * @param array $cells
     * @return boolean
     */
    private function columnsGood($cells){

        foreach($cells as $tgl => $employees){

            foreach($employees as $empKey => $employee){

                if(
                    // $this->shiftLibur($cells, $tgl, $employee) &&
//                     $this->shiftSiang($cells, $tgl, $employee)
                    $this->shiftHarusMaxTigaPuluhPersenMasuk($cells, $tgl, $employee) &&
                    $this->shiftSeniorHarusAdaYangJaga($cells, $tgl, $empKey, $employee)
//                    $this->shiftPagi($cells, $tgl, $employee)
                ){

                }else{
                    return false;
                }
            }

        }

        return true;

    }

    /**
     * Karu constraint
     * libur tiap hari minggu dan tiap hari Pagi
     *
     * @param $tglKey
     * @param $employee
     * @return bool
     */
    private function karuConstraint($tglKey, $employee){

        if($employee['schedule'] === null){
            return true;
        }

        if($employee['employee']['jabatan'] === 'karu'){

            $date       = $tglKey+1;
            $isSunday   = date('w', strtotime($this->month . '/' . $date . '/' . $this->year)) == 0 ? true : false;

            if($isSunday && $employee['schedule'] !== 'L'){
                return false;
            }elseif(!$isSunday && $employee['schedule'] !== 'P'){
                return false;
            }
        }

        return true;
    }

    /**
     * Libur tidak boleh gandeng 2x atau lebih
     *
     * @param $cells
     * @param $tgl
     * @param $empKey
     * @param $employee
     * @return bool
     */
    private function liburTidakBolehGandengConstraint($cells, $tgl, $empKey, $employee){

        if($employee['schedule'] !== 'L' || $employee['employee']['jabatan'] === 'karu'){
            return true;
        }

         if(!isset($cells[$tgl - 1])){
             return true;
         }

         if(
             $cells[$tgl - 1][$empKey]['schedule'] === $employee['schedule']
         ){
             return false;
         }

        return true;

    }

    /**
     * Shift tidak boleh gandeng 3 kali
     * MMM, LLL, SSS
     *
     * @param $cells
     * @param $tgl
     * @param $empKey
     * @param $employee
     * @return bool
     */
    private function shiftTidakBolehGandengTigaKaliConstraint($cells, $tgl, $empKey, $employee){

        if($employee['schedule'] === null || $employee['employee']['jabatan'] === 'karu'){
            return true;
        }

        if(!isset($cells[$tgl - 2])){
            return true;
        }

        if(
            $cells[$tgl - 2][$empKey]['schedule'] === $cells[$tgl - 1][$empKey]['schedule'] &&
            $cells[$tgl - 1][$empKey]['schedule'] === $employee['schedule']
        ){
            return false;
        }

        return true;

    }

    /**
     * Shift tidak boleh dari malam ke pagi
     * M -> P
     *
     * @param $tgl
     * @param $empKey
     * @param $employee
     * @return bool
     */
    private function shiftTidakBolehDariMalamKePagiConstraint($cells, $tgl, $empKey, $employee){

        if($employee['schedule'] === null || $employee['employee']['jabatan'] === 'karu'){
            return true;
        }

        if(!isset($cells[$tgl - 1])){
            return true;
        }

        if(
            $cells[$tgl - 1][$empKey]['schedule'] === 'M' &&
            $employee['schedule']        === 'P'
        ){
            return false;
        }

        return true;

    }

    /**
     * Shift tidak boleh dari malam ke pagi
     * M -> P
     *
     * @param $tgl
     * @param $empKey
     * @param $employee
     * @return bool
     */
    private function shiftTidakBolehDariMalamMalam($cells, $tgl, $empKey, $employee){

        if($employee['schedule'] === null || $employee['employee']['jabatan'] === 'karu'){
            return true;
        }

        if(!isset($cells[$tgl - 1])){
            return true;
        }

        if(
            $cells[$tgl - 1][$empKey]['schedule'] === 'M' &&
            $employee['schedule']        === 'M'
        ){
            return false;
        }

        return true;

    }

    private function formatMML($tgl, $empKey, $employee){
        if($employee['schedule'] === null || $employee['employee']['jabatan'] === 'karu'){
            return true;
        }

        $dayCount   = date('t', strtotime($this->month . '/1/' . $this->year));
        $jmlMinggu  = $this->countSunday;
        $jarakHari  = $dayCount / $jmlMinggu;

        $tgll = $empKey+1+$tgl;

        if($tgll % $jarakHari === 4 || $tgll % $jarakHari === 5){
            if($employee['schedule'] !== 'M'){
                return false;
            }
        }elseif($tgll % $jarakHari === 0){
            if($employee['schedule'] !== 'L'){
                return false;
            }
        }

        return true;
    }

    /**
     * Global libur state per row
     *
     * @var array
     */
    private $libur;

    /**
     * Jumlah libur sesuai dengan jumlah hari minggu
     *
     * @param $cells
     * @param $tgl
     * @param $empKey
     * @param $employee
     * @return bool
     */
    private function jumlahLiburSesuaiJumlahMinggu($cells, $tgl, $empKey, $employee){
        
//        if(!isset($employee['employee'])){
//            var_dump($cells);
//            die();
//        }

        if(
            $employee['schedule'] !== null ||
            $employee['employee']['jabatan'] === 'karu'
        ){
            return true;
        }

        $empSchedule = [];
        foreach($cells as $k => $v){
            if(isset($v[$empKey]['schedule']) && $v[$empKey]['schedule'] !== null){
                $empSchedule[] = $v[$empKey]['schedule'];
            }
        }

        if(!empty($empSchedule)){
            $schedule = array_count_values($empSchedule);
            if(isset($schedule['L']) && $schedule['L'] >= $this->countSunday){
                $this->libur[$empKey] = true;
                return false;
            }
//
//            if($tgl > 25){
//                return true;
//            }
        }

        return true;
    }

    /**
     * Tiap shift harus maksimal 30 masuk
     *
     * @param $cells
     * @param $tgl
     * @param $employee
     * @return bool
     */
    private function shiftHarusMaxTigaPuluhPersenMasuk($cells, $tgl, $employee){

        if($employee['schedule'] === null || $employee['employee']['jabatan'] !== 'anggota'){
            return true;
        }

        $anggotas = array_filter($cells[$tgl], function($arr){
            return $arr['employee']['jabatan'] == 'anggota';
        });

        $jadwalAnggota      = array_column($anggotas, 'schedule');

        // Tiap shift anggota yang masuk bagi rata max 30% dari jumlah
        $filterJadwalNull = array_filter($jadwalAnggota);

        if(!empty($filterJadwalNull)){

            $hitungJadwalYgSama = array_count_values($filterJadwalNull);

            if(isset($hitungJadwalYgSama[$employee['schedule']])){
                if($employee['schedule'] !== 'L'){
                    if($hitungJadwalYgSama[$employee['schedule']] > count($anggotas) * 30/100){
                        return false;
                    }
                }else{
                    if($hitungJadwalYgSama[$employee['schedule']] > count($anggotas) * 10/100){
                        return false;
                    }
                }
            }

        }

        return true;

    }

    private function shiftPagi($cells, $tgl, $employee){
        $shift  = 10;

        if($employee['schedule'] !== 'P' || $employee['employee']['jabatan'] === 'karu'){
            return true;
        }

        $anggotas = array_filter($cells[$tgl], function($arr){
            return $arr['employee']['jabatan'] !== 'karu';
        });

        $jadwalAnggota      = array_column($anggotas, 'schedule');
        $filterJadwalNull = array_filter($jadwalAnggota);

        if(!empty($filterJadwalNull)){

            $hitungJadwalYgSama = array_count_values($filterJadwalNull);
            if(isset($hitungJadwalYgSama[$employee['schedule']])){
                if($hitungJadwalYgSama[$employee['schedule']] > $shift){
                    return false;
                }
            }

        }

        return true;
    }

    private function shiftSiang($cells, $tgl, $employee){
        $shift  = 10;

        if($employee['schedule'] === null || $employee['schedule'] !== 'S' || $employee['employee']['jabatan'] === 'karu'){
            return true;
        }

        $anggotas = array_filter($cells[$tgl], function($arr){
            return $arr['employee']['jabatan'] !== 'karu';
        });

        $jadwalAnggota      = array_column($anggotas, 'schedule');

        // Tiap shift anggota yang masuk bagi rata max 30% dari jumlah
        $filterJadwalNull = array_filter($jadwalAnggota);

        if(!empty($filterJadwalNull)){

            $hitungJadwalYgSama = array_count_values($filterJadwalNull);
            if(isset($hitungJadwalYgSama[$employee['schedule']])){
                if($hitungJadwalYgSama[$employee['schedule']] > $shift){
                    return false;
                }
            }

        }

        return true;
    }

    private function shiftMalam($cells, $tgl, $employee){
        $shift  = 3;

        if($employee['schedule'] === null || $employee['schedule'] !== 'M' || $employee['employee']['jabatan'] === 'karu'){
            return true;
        }

        $anggotas = array_filter($cells[$tgl], function($arr){
            return $arr['employee']['jabatan'] !== 'karu';
        });

        $jadwalAnggota      = array_column($anggotas, 'schedule');

        // Tiap shift anggota yang masuk bagi rata max 30% dari jumlah
        $filterJadwalNull = array_filter($jadwalAnggota);

        if(!empty($filterJadwalNull)){

            $hitungJadwalYgSama = array_count_values($filterJadwalNull);
            if(isset($hitungJadwalYgSama[$employee['schedule']])){
                if($hitungJadwalYgSama[$employee['schedule']] >= $shift){
                    return false;
                }
            }

        }

        return true;
    }

    /**
     * Shift harus ada yang jaga
     *
     * @param $cells
     * @param $tgl
     * @param $employee
     * @return bool
     */
    private function shiftSeniorHarusAdaYangJaga($cells, $tgl, $empKey, $employee){

        if(
            $employee['schedule'] === null ||
            $employee['schedule'] === 'L' ||
            $employee['employee']['jabatan'] !== 'senior'
        ){
            return true;
        }

        $shifts = $this->shifts;

        if(isset($this->libur[$empKey]) && $this->libur[$empKey] === TRUE){
            unset($shifts[3]);
        }

        $anggotas = array_filter($cells[$tgl], function($arr){
            return $arr['employee']['jabatan'] === 'senior';
        });

        $jadwalAnggota      = array_column($anggotas, 'schedule');
        $filterJadwalNull   = array_filter($jadwalAnggota);

        if(!empty($filterJadwalNull)){
            $hitungJadwalYgSama = array_count_values($filterJadwalNull);

            if(count($hitungJadwalYgSama) < count($this->shifts)){
                $diff = array_diff($shifts, array_keys($hitungJadwalYgSama));

                if(!empty($diff)){
                    if(
                        isset($hitungJadwalYgSama[$employee['schedule']]) &&
                        $hitungJadwalYgSama[$employee['schedule']] > 1
                    ){
                        return false;
                    }
                }

            }
        }

        return true;
    }

    /**
     * Map cells by Employee
     *
     * @param $cellsx
     * @return array
     */
    private function mapByEmployee($cellsx){
        $cells      = [];

        foreach($cellsx as $y => $columns){
            foreach($columns as $x => $row){
                $cells[$x]              = $row['employee'];
                foreach($cellsx as $k => $r){
                    $cells[$x]['schedules'][] = [
                        'schedule'      => $r[$x]['schedule']
                    ];
                }
            }
        }

        $this->cells['data'] = $cells;
        return $cells;
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
     * @return int
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
     * @throws Exception
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