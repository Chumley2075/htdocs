<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {

    $user_id = escapeshellarg($_POST['user_id']);

    
    $python  = "/var/www/py311env/bin/python3.11";  
    $delete_script = "/var/www/html/htdocs/ryan/yilma/deleteFace.py";
    $retrain_script = "/var/www/html/htdocs/ryan/yilma/trainer.py";

    exec("$python $delete_script $user_id 2>&1", $delete_out, $delete_code);

    if ($delete_code === 0) {
        $cmd = "$python $retrain_scriptc > /var/log/retrain.log 2>&1 &";
        exec($cmd);
        echo "Face data deleted, model retraining started in background.";
    } else {
        echo "Delete failed:\n" . implode("\n", $delete_out);
    }
    exit();
}

echo "Invalid request.";
