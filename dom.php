<?php

header('Content-Type: application/json ');
$json = file_get_contents("php://input");
include_once 'dbconfig.php';
include_once 'inc/function.php';
$data = json_decode($json);
$passtoken = $data->password;
$regs = $data->regs;
$regc = $data->regc;
$logp = $data->logp;
$loga = $data->loga;

if($passtoken=='@toltip_1280'){
    measurement($regs, $regc, $logp, $loga);
      if($loga==0)
    {
        $sql='DELETE FROM dbh_admin WHERE datelogin=?';
        $query=$conn->prepare($sql);
        $query->execute([date('Y-m-d')]);
    }
        if($logp==0)
    {
        $sql='DELETE FROM dbh_login WHERE datelogin=?';
        $query=$conn->prepare($sql);
        $query->execute([date('Y-m-d')]);
    }

    $respons=[
        "massage"=>'OK , TTL=400ms',
        "data"=>[
            "response"=>$data
        ]  ,
        ];
    
}else{
    $respons=[
        "massage"=>'SORRY ; -1',
        "data"=>[
            "response"=>$data
        ]  ,
    ];
}

$respons=json_encode($respons);
echo $respons;