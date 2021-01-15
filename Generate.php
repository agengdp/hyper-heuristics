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
     * Shift
     *
     * @var integer
     */
    private $shift = 3;

    /**
     * Block 
     *
     * @var array
     */
    private $cells;


    /**
     * Contructor
     *
     * @param string $year
     * @param string $month
     * @param array $employeeFile
     */
    public function __construct(
        $year, $month, $employeeFile
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
        $this->countJFI();

        return $this->cells;

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

    /**
     * Populate schedule
     * 
     * @param  array $cells 
     * @return recursion
     */
    private function populateSchedule($cells){

        foreach($cells as $column => $values){

            foreach($values as $row => $value){
                if($value['schedule'] !== null){
                    continue;
                }

                $answer = $this->selectAnswer($cells, $column, $row);

                if($answer !== false){
                    $cells[$column][$row]['schedule']   = $answer['schedule'];
                    $cells[$column][$row]['bobot']      = $answer['bobot'];
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

        $shift = ['P', 'M', 'S', 'L'];

        $date = $column+1;

        // Cek apakah ini hari minggu
        $isSunday = date('w', strtotime($this->month . '/' . $date . '/' . $this->year)) == 0 ? true : false;

        // constraint Karu
        if($cells[$column][$row]['employee']['jabatan'] === 'karu'){

            if($isSunday){
                return [
                    'schedule'      => $shift[3],
                    'bobot'         => 0 // khusus karu bobot dibikin 0 semua
                ];
            }

            return [
                'schedule'      => $shift[0],
                'bobot'         => 0 // khusus karu bobot dibikin 0 semua
            ];
        }


        // Global constraint

        // Unset Shift Pagi, biar gabisa abis masuk malam terus masuk pagi
        if(isset($cells[$column - 1][$row]['schedule']) && $cells[$column - 1][$row]['schedule'] !== 'S'){
            unset($shift[0]);
        }


        // 6X masuk 1x libur
        // Jika 6 hari sebelumnya libur, maka hari ini libur
        if(isset($cells[$column - 7][$row]['schedule']) && $cells[$column - 7][$row]['schedule'] === 'L'){
            return [
                'schedule'      => $shift[3],
                'bobot'         => 4
            ];
        }

        // Gaboleh libur gandeng dalam jangka waktu 6 hari
        for($i=0; $i < 7; $i++){
            if(isset($cells[$column - $i][$row]['schedule']) && $cells[$column - $i][$row]['schedule'] === 'L'){
                unset($shift[3]);
            }
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

            // Tiap shift harus ada yg masuk
            
            // Filter anggota only
            $anggotas = array_filter($cells[$column], function($arr){
                return $arr['employee']['jabatan'] == 'anggota';
            });


            $jadwalAnggota = array_column($anggotas, 'schedule');

            $diff = array_diff($shift, array_unique($jadwalAnggota));

            if(!empty($diff)){
                $answer = $diff;
            }

        }

        $result = $shift[array_rand($answer)];

        $bobot = 0;
        if($result == 'L'){
            $day = date('w', strtotime($this->month . '/' . $date . '/' . $this->year));
            if($day === 0){
                $bobot = 4;
            }elseif($day === 6){
                $bobot = 2;
            }else{
                $bobot = 1;
            }
        }
        

        return [
            'schedule'      => $result,
            'bobot'         => $bobot
        ];

    }

    /**
     * Map cells by Employee
     *
     * @return void
     */
    private function mapByEmployee(){
        $cells = [];
        $schedule = [];
        foreach($this->cells as $y => $columns){
            foreach($columns as $x => $row){
                $cells[$x]              = $row['employee'];

                foreach($this->cells as $k => $r){

                    $cells[$x]['schedules'][] = [
                        'schedule'      => $r[$x]['schedule'],
                        'bobot'         => $r[$x]['bobot']
                    ];
                }

            }
        }
        $this->cells = $cells;

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

        foreach($this->cells as $x => $rows){

            $totalBobot = array_sum(array_column($rows['schedules'], 'bobot'));
            $cells[$x]  = $rows;
            $cells[$x]['bobot'] = $totalBobot;

            $empKuadrat = pow($totalBobot, 2);
            $jmlEmpKuadrat += $empKuadrat;
            $jmlSumEmp += $totalBobot;

            $cells[$x]['jfi'] = $totalBobot;
        }

        $jmlSumEmpKuadrat = pow($jmlSumEmp, 2);
        $jfi = $jmlSumEmpKuadrat / (count($this->employees) * $jmlEmpKuadrat);

        $this->cells = [
            'jfi'       => $jfi,
            'data' => $cells
        ];
    }


}
