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
    private $blocks;


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
        $this->blocks       = $this->generateBlocks();

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
                'nama'      => $mm[0],
                'jabatan'   => strtolower($mm[1])
            ];
        }
        fclose($employees);

        return $this->employees = $emp;        
    }

    /**
     * Generate blocks
     *
     * @return void
     */
    private function generateBlocks(){
        $dayCount = date('t', strtotime($this->month . '/1/' . $this->year));
        
        $blocks = [];

        for($x = 0; $x < $dayCount; $x++){
            
            for($y = 0; $y < count($this->employees); $y++){
                $blocks[$x][$y] = null;
            }
        }

        return $this->blocks = $blocks;

    }

    public function solve(){
        return $this->employees;
    }


}

$init = new Generate(2020, 11, 'data/asoka.txt');
var_dump($init->solve());