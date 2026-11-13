<?php
require __DIR__.'/../../vendor/autoload.php';
use OpenSpout\Common\Entity\Row; use OpenSpout\Writer\XLSX\Writer;
$w = new Writer; $w->openToFile($argv[1]);
foreach ([['Party Name','Mobile No','City','Status','Deal Value'],['Import One','9811100001','Pune','Interested','2,50,000'],['Import Two','9811100002','Surat','',''],['Clash Firm','9811100099','Indore','','']] as $r) $w->addRow(Row::fromValues($r));
$w->close();
