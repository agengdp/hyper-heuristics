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
                    'schedule'      => null
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
        return $this;

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
                    $cells[$column][$row]['schedule'] = $answer;
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

        // Cek apakah ini hari minggu
        $isSunday = date('w', strtotime($this->month . '/. $column./' . $this->year)) == 0 ? true : false;

        // constraint Karu
        if($cells[$column][$row]['employee']['jabatan'] === 'karu'){

            if($isSunday){
                return $shift[3];
            }

            return $shift[0];
        }


        // Global constraint

        // Unset Shift Pagi, biar gabisa abis masuk malam terus masuk pagi
        $previousShift = $cells[$column - 1][$row]['schedule'];
        if(isset($previousShift) && $previousShift !== 'S'){
            unset($shift[0]);
        }


        // 6X masuk 1x libur
        // Jika 6 hari sebelumnya libur, maka hari ini libur
        if($cells[$column - 6][$row]['schedule'] === 'L'){
            return $shift[3];
        }

        // Senior Constraint
        if($cells[$column][$row]['employee']['jabatan'] === 'senior'){

            // Tiap shift harus ada yg masuk
            
            // Filter senior only
            $seniors = array_filter($cells[$column], function($arr){
                return $arr['employee']['jabatan'] == 'senior';
            });


            $jadwalSenior = array_column($seniors, 'schedule');

            // $shift = array_diff($shift, array_unique($jadwalSenior));

        }


        var_dump($jadwalSenior);
        echo "----------";

        // var_dump($shift);

        if(empty($shift)){
            die();
        }

        return $shift[array_rand($shift)];

    }


}

$init = new Generate(2020, 11, 'data/asoka.txt');
$init->initalize();

