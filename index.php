<?php
  session_start();

  require(dirname(__FILE__) . '/include/functions.php');
  require(dirname(__FILE__) . '/include/connect.php');

  // first time install
  if(($_SERVER[REQUEST_URI] != "/index.php?installation") && (isInstalled($bdd) == false)) {
    header("Location: index.php?installation");
    exit(-1);
  }
  
  // Disconnecting ?
  if(isset($_GET['logout'])){
    session_destroy();
    header("Location: .");
    exit(-1);
  }

  // Read ovpn file contents
  $ovpn_filename= file_get_contents("./client-conf/windows/filename");

  // Get the Windows instruction file 
  if(isset($_POST['windows_instruction_get'])) {
      $download_file_name1 = "Download and install the OpenVPN GUI (Windows).pdf";
      $file_folder1  = "windows";
      $file_full_path1  = './client-conf/' . $file_folder1 . '/' . $download_file_name1;
      header("Content-type: application/pdf");
      header("Content-disposition: attachment; filename=$download_file_name1");
      header("Pragma: no-cache");
      header("Expires: 0");
      readfile($file_full_path1);
      exit;
     }

  // Get the MAC instruction file 
  if(isset($_POST['mac_instruction_get'])) {
    
      $download_file_name2 = "Download and install the OpenVPN GUI (MAC).pdf";
      $file_folder2  = "osx-viscosity";
      $file_full_path2  = './client-conf/' . $file_folder2 . '/' . $download_file_name2;
      header("Content-type: application/pdf");
      header("Content-disposition: attachment; filename=$download_file_name2");
      header("Pragma: no-cache");
      header("Expires: 0");
      readfile($file_full_path2);
      exit;
    }

  // Get configuration file from admin page
  if(isset($_GET['admin_configuration_get'])  && !empty($_SESSION['admin_id']) ) {
    $file_name = "client.ovpn";
    $file_folder  = "windows";
    $file_full_path  = './client-conf/' . $file_folder . '/' . $file_name;
    header("Content-type: application/ovpn");
    header("Content-disposition: attachment; filename=$ovpn_filename.ovpn");
    header("Pragma: no-cache");
    header("Expires: 0");
    readfile($file_full_path);
    exit;
  }

  // Get the configuration files from configuration page
  if(isset($_POST['configuration_get'], $_POST['configuration_username'], $_POST['configuration_pass']) && !empty($_POST['configuration_pass'])) {
    $req = $bdd->prepare('SELECT * FROM user WHERE user_id = ?');
    $req->execute(array($_POST['configuration_username']));
    $data = $req->fetch();

    // Error ?
    if($data && passEqual($_POST['configuration_pass'], $data['user_pass'])) {
      $file_name = "client.ovpn";
      $file_folder  = "windows";
      $file_full_path  = './client-conf/' . $file_folder . '/' . $file_name;
      header("Content-type: application/ovpn");
      header("Content-disposition: attachment; filename=$ovpn_filename.ovpn");
      header("Pragma: no-cache");
      header("Expires: 0");
      readfile($file_full_path);
      exit;
    }
    else {
      $error = true;
    }
  }

  // Admin login attempt ?
  else if(isset($_POST['admin_login'], $_POST['admin_username'], $_POST['admin_pass']) && !empty($_POST['admin_pass'])){

    $req = $bdd->prepare('SELECT * FROM admin WHERE admin_id = ?');
    $req->execute(array($_POST['admin_username']));
    $data = $req->fetch();

    // Error ?
    if($data && passEqual($_POST['admin_pass'], $data['admin_pass'])) {
      $_SESSION['admin_id'] = $data['admin_id'];
      header("Location: index.php?admin");
      exit(-1);
    }
    else {
      $error = true;
    }
  }
?>

<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />

    <title>OpenVPN-Admin</title>

    <link rel="stylesheet" href="vendor/bootstrap/dist/css/bootstrap.min.css" type="text/css" />
    <link rel="stylesheet" href="vendor/x-editable/dist/bootstrap3-editable/css/bootstrap-editable.css" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap-table/dist/bootstrap-table.min.css" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap-table/dist/extensions/filter-control/bootstrap-table-filter-control.css" type="text/css" />
    <link rel="stylesheet" href="css/index.css" type="text/css" />

    <link rel="icon" type="image/png" href="css/icon.png">
  </head>
  <body class='container-fluid'>
  <?php

    // --------------- INSTALLATION ---------------
    if(isset($_GET['installation'])) {
      if(isInstalled($bdd) == true) {
        printError('OpenVPN-admin is already installed. Redirection.');
        header( "refresh:3;url=index.php?admin" );
        exit(-1);
      }

      // If the user sent the installation form
      if(isset($_POST['admin_username'])) {
        $admin_username = $_POST['admin_username'];
        $admin_pass = $_POST['admin_pass'];
        $admin_repeat_pass = $_POST['repeat_admin_pass'];

        if($admin_pass != $admin_repeat_pass) {
          printError('The passwords do not correspond. Redirection.');
          header( "refresh:3;url=index.php?installation" );
          exit(-1);
        }

        // Create the initial tables
        $migrations = getMigrationSchemas();
        foreach ($migrations as $migration_value) {
          $sql_file = dirname(__FILE__) . "/sql/schema-$migration_value.sql";
          try {
            $sql = file_get_contents($sql_file);
            $bdd->exec($sql);
          }
          catch (PDOException $e) {
            printError($e->getMessage());
            exit(1);
          }

          unlink($sql_file);

          // Update schema to the new value
          updateSchema($bdd, $migration_value);
        }

        // Generate the hash
        $hash_pass = hashPass($admin_pass);

        // Insert the new admin
        $req = $bdd->prepare('INSERT INTO admin (admin_id, admin_pass) VALUES (?, ?)');
        $req->execute(array($admin_username, $hash_pass));

        rmdir(dirname(__FILE__) . '/sql');
        printSuccess('Well done, OpenVPN-Admin is installed. Redirection.');
        header( "refresh:3;url=index.php?admin" );
      }
      // Print the installation form
      else {    
        require(dirname(__FILE__) . '/include/html/menu.php');
        require(dirname(__FILE__) . '/include/html/form/installation.php');
      }
      exit(-1);
    }

    // --------------- CONFIGURATION ---------------
    if(!isset($_GET['admin'])) {
      if(isset($error) && $error == true)
        printError('Login error');

      require(dirname(__FILE__) . '/include/html/menu.php');
      require(dirname(__FILE__) . '/include/html/form/configuration.php');
    }


    // --------------- LOGIN ---------------
    else if(!isset($_SESSION['admin_id'])){
      if(isset($error) && $error == true)
        printError('Login error');

      require(dirname(__FILE__) . '/include/html/menu.php');
      require(dirname(__FILE__) . '/include/html/form/login.php');
    }

    // --------------- GRIDS ---------------
    else{
  ?>
      <nav class="navbar navbar-default">
        <div class="row col-md-12">
          <div class="col-md-6">
            <p class="navbar-text signed">Signed in as <?php echo $_SESSION['admin_id']; ?>
            </div>
            <div class="col-md-6">
              <a class="navbar-text navbar-right" href="index.php?logout" title="Logout"><button class="btn btn-danger">Logout <span class="glyphicon glyphicon-off" aria-hidden="true"></span></button></a>
              <a class="navbar-text navbar-right" href="index.php" title="Configuration"><button class="btn btn-default">Configurations</button></a>
              <a class="navbar-text navbar-right" href="index.php?admin_configuration_get" title="Get Config File"><button class="btn btn-default">Get Config File</button></a>
            </p>
          </div>
        </div>
      </nav>

  <?php
      require(dirname(__FILE__) . '/include/html/grids.php');
    }
  ?>  
     <div id="message-stage">
        <!-- used to display application messages (failures / status-notes) to the user -->
     </div>
  </body>
</html>
