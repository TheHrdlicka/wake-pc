<?php
// ini_set('display_errors', 1);
function ping($host, $timeout = 1) {
	//Na linuxu je tuším potřeba mít -c 2 místo nka.
	$com = "";
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		$com = "ping -n 2 -w 500 ";
	} else {
		$com = "ping -c 2 ";
	}
	exec($com . $host, $output, $result);
	if ($result == 0)
		return true;
	else
		return false;

}
$status = "";
if(isset($_POST["addName"]))
{
	if(!isset($_POST["addIp"]) || !isset($_POST["addMac"]))
	{
		$status .= "IP or MAC failed to validate!<br>";
	} else 
	{
		$mac = str_replace("-",":",$_POST["addMac"]);
		if(!filter_var($_POST["addIp"], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($mac, FILTER_VALIDATE_MAC))
			{
				$status .= "IP or MAC failed to validate!<br>" . $mac . "<br>" . $_POST["addIp"];
			} else
			{
			$addName = $_POST["addName"];
			preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $addName);
			$configFile = fopen("config.ini", "a");
			fwrite($configFile, "[" . $addName . "]" . PHP_EOL); 
			fwrite($configFile, "host=" . $_POST["addIp"] . PHP_EOL);  
			fwrite($configFile, "mac=" . $mac . PHP_EOL); 
			fwrite($configFile, "password=" . password_hash($_POST["addPassword"],PASSWORD_DEFAULT) . PHP_EOL);
			fwrite($configFile, "pcName=\"" . $_POST["addName"] . "\"" . PHP_EOL);
			fwrite($configFile, "pingPort=1234" . PHP_EOL);
			fwrite($configFile, "wolPort=9" . PHP_EOL);
			fwrite($configFile, "broadcast=10.0.1.255" . PHP_EOL);
			fwrite($configFile,  PHP_EOL);
			fclose($configFile);
			$status .= "Added PC ".$_POST["addName"]."<br>";
			}
	}
}
if (($config = parse_ini_file("config.ini", true)) == false) {
	// uh-oh, failed ot read ini file
	$status .= "Missing, empty or corrupt configuration file!<br>";
} else {
	// config.ini read successfully, let's break it up to sections
	$globalConfig = $config['global'];
	unset($config['global']);
	$pcConfigs = $config;
	// get config of selected PC
	if(count($config) != 0)
	{
	$pcSelected = isset($_GET['pc']) ? $_GET['pc'] : array_key_first($config);
		$pcConfig = $pcConfigs[$pcSelected];
		// ping PC
		$online = ping($pcConfig['host'],$globalConfig["pingTimeout"]);
		// check if we want to wake or just fetching page
			if (isset($_POST['wake'])) {
				// check password if needed
				if ((isset($_POST['pwd']) && password_verify($_POST['pwd'], $pcConfig['password'])) ||
					$pcConfig['password'] == ""
				) {
				// password ok/not needed, lets send WOL
				$command = "wakeonlan " . $pcConfig["mac"] . " " . $pcConfig["broadcast"];
				exec($command,$output,$result);
				// check the status code of the above command, if 0 say OK otherwise print output
				$status .= trim($result) == "0" ? '<br>Magic packet sent to ' . $pcConfig['pcName'] : '<br>Failed to send WOL packet: <pre>' . implode("<br>", $output) . '</pre>';
			} else $status .=  '<br>Incorrect password';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
	<!-- Bootstrap 5.0.2 CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
	<!-- Bootstrap 5.0.2 JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
	<script src='https://code.jquery.com/jquery-3.3.1.slim.min.js'></script>
<!-- Popper JS -->
<script src='https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js'></script>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta charset="utf-8">
	<title>⏰ Wake PC</title>
	<style>
		.container {
  padding: 2rem 0rem;
}

@media (min-width: 576px) {
  .modal-dialog {
    max-width: 400px;
  }
  .modal-dialog .modal-content {
    padding: 1rem;
  }
}
.modal-header .close {
  margin-top: -1.5rem;
}

.form-title {
  margin: -2rem 0rem 2rem;
}

.btn-round {
  border-radius: 3rem;
}

.delimiter {
  padding: 1rem;
}

.social-buttons .btn {
  margin: 0 0.5rem 1rem;
}

.signup-section {
  padding: 0.3rem 0rem;
}
	</style>
</head>

<body>
	<div class="card" style="max-width:240px; margin:auto; margin-top: 2em">
		<div class="card-body">
			<h4 class="card-title mb-3">Wake PC</h4>
			<form action="" method="post">
				<select id="pc-select" class="form-select mb-3" name="pc" onchange="pcSelected()">
					<?php
					foreach ($pcConfigs as $key => $value) {
						echo '<option value="' . $key . '" ' . ($key === $pcSelected ? "selected" : "") . '>' . $value["pcName"] . '</option>';
					}
					?>
				</select>
				<?php
				if ($pcConfig['password'] != "") {
					echo '
						<div class="form-group mb-3" >
							<label for="pwd">Password</label>
							<input type="password" class="form-control" id="pwd" name="pwd" placeholder="Password">
						</div>
						';
				}
				?>
				<?php
				echo '<p class="text-center" style="cursor: pointer;">' . $pcConfig['host'] . '</p>';
				if ($online) {
					echo '<p class="text-center" style="color:green; cursor: pointer;" onclick="reload()">⬤ Online</p>';
				} else {
					echo '<p class="text-center" style="color:red; cursor: pointer;" onclick="reload()">❌ Offline</p>';
				}
				?>
				<div class="d-grid mb-3">
					<button class="btn btn-primary btn-block m-2" type="submit" id="wake-btn" name="wake" value="wake"><?= $globalConfig['wakeButtonText'] ?></button>
					<button type="button" id="addButton" class="btn btn-secondary btn-block m-2" data-toggle="modal" data-target="#loginModal">Add New</button>  
				</div>
			</form>
			<div class="text-center"><?= $status ?></div>
		</div>
	</div>
	<div class="container">

	</div>

	<div class="modal fade" id="loginModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <div class="form-title text-center">
          <h4>Add New Target</h4>
        </div>
        <div class="d-flex flex-column text-center">
          <form action="index.php" method="POST"> 
            <div class="form-group">
				<label for="addName">PC Nickname</label>
              <input type="text" class="form-control" id="addName" name="addName" placeholder="PC Name" required>
            </div>
            <div class="form-group">
			<label for="addMac">MAC</label>
              <input type="text" class="form-control" id="addMac" name="addMac" value="XX-XX-XX-XX-XX-XX" pattern= "^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})|([0-9a-fA-F]{4}\.[0-9a-fA-F]{4}\.[0-9a-fA-F]{4})$" required>
            </div>
			<div class="form-group">
			<label for="addIp">IP</label>
              <input type="text" class="form-control" id="addIp" name="addIp" value="10.0.1.XX" pattern="^(([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|2[0-5][0-5]|2[0-4]\d)$" required>
            </div>
			<div class="form-group">
			<label for="addPassword">Password</label>
              <input type="password" class="form-control" id="addPassword" name="addPassword">
            </div>
            <input class="btn btn-primary btn-block m-2" type="submit" value="Add"></input>
          </form>
          
      </div>
    </div>
  </div>
</div>
</body>

</html>
<script>
	function pcSelected() {
		selected = document.getElementById("pc-select").value;
		var url = new URL(window.location);
		url.searchParams.set('pc', selected);
		window.open(url, "_self");
	}

	function reload() {
		window.location.reload();
	}

	 	

$( "#addButton" ).click(function() {             
$('#loginModal').modal('show');
  $(function () {
    $('[data-toggle="tooltip"]').tooltip()
  })
});
</script>