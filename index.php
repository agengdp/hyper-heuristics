<?php

include 'Generate.php';
$schedule = new Generate(2020, 12, 'data/asoka.txt');
$schedule->initalize();
// $schedule->reinforcementLearning(1000);
// $schedule->hillClimbing(1000);

$schedules = $schedule->cells;
//633016

?>

<style type="text/css">
    table, th, td {
  border: 1px solid black;
}
    table {
      border-collapse: collapse;
    }
</style>

JFI: <?= $schedules['jfi'] ?>
<table width="100%">
    <thead>
        <tr>
            <th>
                X
            </th>
            <th>Nama</th>
            <?php $day = 0; foreach($schedules['data'][0]['schedules'] as $schedule): $day++ ?>
                <th><?=$day?></th>
            <?php endforeach; ?>
            <th>
                Total
            </th>
        </tr>
    </thead>
    <tbody>

        <?php
            $totalColumn = [];
        ?>
        <?php foreach($schedules['data'] as $key => $schedule): ?>
            <tr>
                <td><?= $key ?></td>
                <td>
                    <?= $schedule['nama']; ?><br>
                    <?= $schedule['jabatan']; ?><br>
                    <?= $schedule['spesialisasi']; ?><br>
                </td>
                <?php $total = [
                    'P' => 0,
                    'S' => 0,
                    'M' => 0,
                    'L' => 0
                ];?>
                <?php foreach($schedule['schedules'] as $k => $value): ?>
                <?php

                    if(!isset($totalColumn[$k][$value['schedule']])){
                        $totalColumn[$k][$value['schedule']] = 0;
                    };

                    $totalColumn[$k][$value['schedule']]++;
                ?>
                <?php $total[$value['schedule']]++;?>
                    <?php
                        $bg = '#0FF';
                        if($value['schedule'] == 'P'){
                            $bg = '#FF0';
                        }elseif($value['schedule'] == 'L'){
                            $bg = '#F00';
                        }elseif($value['schedule'] == 'S'){
                            $bg = '#0F0';
                        }
                    ?>
                    <td style="text-align: center;background:<?= $bg ?>"><?= $value['schedule']; ?></td>
                <?php endforeach; ?>
                <td>
                    P=<?= $total['P'] ?><br/>
                    S=<?= $total['S'] ?><br/>
                    M=<?= $total['M'] ?><br/>
                    L=<?= $total['L'] ?><br/>
                </td>
            </tr>
        <?php endforeach; ?>
            <tr>
                <td colspan="2" style="text-align: center">Total</td>
                <?php foreach($schedules['data'][0]['schedules'] as $key => $schedule): ?>
                    <td>
                        P: <?= isset($totalColumn[$key]['P']) ? $totalColumn[$key]['P'] : '' ?><br/>
                        S: <?= isset($totalColumn[$key]['S']) ? $totalColumn[$key]['S'] : ''?><br/>
                        M: <?= isset($totalColumn[$key]['M']) ? $totalColumn[$key]['M'] : ''?><br/>
                        L: <?= isset($totalColumn[$key]['L']) ? $totalColumn[$key]['L'] : '' ?>
                    </td>
                <?php endforeach; ?>
                <td></td>
            </tr>
    </tbody>
</table>