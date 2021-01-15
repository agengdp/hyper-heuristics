<?php

include 'Generate.php';
$schedule = new Generate(2008, 02, 'data/asoka.txt');
$schedules = $schedule->initalize();

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

            <?php $day = 0; foreach($schedules['data'][0]['schedules'] as $schedule): $day++ ?>
                <th><?=$day?></th>
            <?php endforeach; ?>
            <th>bobot</th>
        </tr>
    </thead>
    <tbody>
        
        <?php foreach($schedules['data'] as $key => $schedule): ?>            
            <tr>
                <td>
                    <?= $schedule['nama']; ?><br>
                    <?= $schedule['jabatan']; ?><br>
                    <?= $schedule['spesialisasi']; ?><br>
                </td>
                <?php foreach($schedule['schedules'] as $k => $value): ?>
                    <td style="text-align: center"><?= $value['schedule']; ?></td>
                <?php endforeach; ?>
                <td style="text-align:center"><?= $schedule['bobot'] ?></tr>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>