<?php

include 'Generate.php';
$schedule = new Generate(2020, 11, 'data/asoka.txt');
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
<table>
    <thead>
        <tr>
            <th>
                X
            </th>
            <?php foreach($schedules as $key => $schedule): ?>
                <th ><?=$key+1?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach($schedules[0] as $key => $schedule): ?>
            <tr>
                <?php foreach($schedules as $k => $value): ?>

                    <?php if($k == 0) : ?>
                        <td>
                            <?= $schedules[$k][$key]['employee']['nama']; ?><br>
                            <?= $schedules[$k][$key]['employee']['jabatan']; ?><br>
                            <?= $schedules[$k][$key]['employee']['spesialisasi']; ?><br>
                        </td>
                    <?php endif; ?>
                    <td style="text-align: center"><?= $value[$key]['schedule']; ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>