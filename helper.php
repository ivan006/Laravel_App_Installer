<?php
function install($home_path, $connect_to_db, $step, $subdomain_dir, $github_repo_url, $webroot_parent_path){

  // rm -rf *
  // ls -la ..
  // cp -a FlexFile-3/public/. public_html/
  // ln -s /usr/www/users/bluegpyuty  public_html

  $form = array(
    "connect_to_db" => $connect_to_db,
    "step" => $step,
  );

  $result = array();
  // if ($subdomain_dir == "") {
  //   // code...
  // }
  //

  $inputs = array(
    "subdomain_dir" => $subdomain_dir,
    // /usr/home/bluegpyuty/FlexFile-3
    "app_path" => $home_path."/".$subdomain_dir."_app",
    "webroot_path" => $webroot_parent_path,
    "home_path" => $home_path,
    "github_repo_url" => $github_repo_url,
  );
  if ($subdomain_dir !== "") {
    $inputs["webroot_path"] = $inputs["webroot_path"]."/".$subdomain_dir;
  }

  // download core files
  if ($form["step"] == 1) {
    $result = cmd_find_dir_home($result,$inputs,$form);
    $result = cmd_find_webroot($result,$inputs,$form);

    $result = cmd_clear_space_for_the_app($result,$inputs,$form);
    $result = cmd_download_app($result,$inputs,$form);
    $result = cmd_find_app_path($result,$inputs,$form);
    $result = cmd_deploy_webroot_files_part_1($result,$inputs,$form);
  }

  // deploy library files
  if ($form["step"] == 2) {
    $result = cmd_find_app_path($result,$inputs,$form);
    $result = cmd_download_libraries($result,$inputs,$form);
  }

  // deploy configurations
  if ($form["step"] == 3) {
    $result = cmd_find_app_path($result,$inputs,$form);

    $result = cmd_fix_paths_part_1($result,$inputs,$form);
    $result = cmd_fix_file_permissions($result,$inputs,$form);
    $result = cmd_save_db_logins($result,$inputs,$form);
    $result = cmd_generate_key($result,$inputs,$form);

    $result = cmd_depopulate_database($result,$inputs,$form);
    $result = cmd_populate_database($result,$inputs,$form);
  }

  $html = status_html($result);

  return $html;
}

function status_html($elements) {
  ob_start();
  ?>
  <style media="screen">
    /* .td_Wi_50Per td {width: 50%;} */
    .Wi_100Per {width: 100%}
    .td_VA_To td {vertical-align: top;}
    .TaLa_Fix {table-layout: fixed;}

  </style>
  <table class="table table-striped td_VA_To TaLa_Fix Wi_100Per">
  <?php
  foreach ($elements as $key => $element) {
    // code...
    ?>
    <tr>
      <td>
        <?php echo $key+1 .". ".$element[0] ?>:
      </td>
      <td>
        <?php echo $element[1] ?>
      </td>
    </tr>
    <?php
  }

  ?>
  </table>
  <?php

  $result = ob_get_contents();

  ob_end_clean();
  return $result;
}

function shell_find_dir($inputs,$label){
  $result = array(
    "Check $label dir",
  );
  $shell_result = rtrim(shell_exec("cd $inputs; pwd"));
  if ($shell_result !== $inputs) {
    array_push($result,"Fail");
  } else {
    array_push($result,"Success");
  }
  return $result;
}

function shell_write($inputs,$cmd,$label){
  $result = array(
    $label,
  );

  exec(
    "cd $inputs;
    $cmd 2>&1"
    , $outputs
  );

  $result = array(
    $label,
    "<pre>".json_encode($outputs,JSON_PRETTY_PRINT)."</pre>",
  );
  return $result;
}

function abort_install($error){
  header("Location: /Reportify_Installer/index.php?error=$error");
  exit();
}


function cmd_find_dir_home($result,$inputs,$form){
  $cmd_result = shell_find_dir($inputs['home_path'],"home");
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;
}

function cmd_find_webroot($result,$inputs,$form){
  $cmd_result = shell_find_dir($inputs['webroot_path'],"webroot");
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;
}

function cmd_depopulate_database($result,$inputs,$form){

  $app_path = $inputs['app_path'];
  // "php artisan migrate",
  $cmd_result = shell_write(
    $app_path,
    "php artisan migrate:reset",
    "Depopulate database"
  );
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;
}

function cmd_clear_space_for_the_app($result,$inputs,$form){
  $app_name = $inputs['subdomain_dir']."_app";
  $cmd_result = shell_write(
    $inputs['home_path'],
    "rm -rf '$app_name';
    mkdir $app_name",
    "Prepare app folder"
  );
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;
}

function cmd_download_app($result,$inputs,$form){
  $app_path = $inputs['app_path'];
  $cmd_result = shell_write(
    $app_path,
    "git clone ".$inputs['github_repo_url']." .",
    "Download app"
  );
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;
}

function cmd_find_app_path($result,$inputs,$form){

  $app_path = $inputs['app_path'];
  $cmd_result = shell_find_dir($app_path,"app");
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;
}

function cmd_deploy_webroot_files_part_1($result,$inputs,$form){
  $app_path = $inputs['app_path'];
  $cmd_result = shell_write(
    $inputs['webroot_path'],
    "cp -a $app_path/public/* ./;
    cp -r $app_path/public/.htaccess ./",
    "Deploy webroot files"
  );
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;

}

function cmd_fix_paths_part_1($result,$inputs,$form){

  $app_path = $inputs['app_path'];
  $str1_1 = "__DIR__.'";
  $str1_1 = preg_quote($str1_1, '/');
  $str1_2 = "'$app_path";
  $str1_2 = preg_quote($str1_2, '/');

  $app_path = $inputs['app_path'];
  $cmd_result = shell_write(
    $app_path,
    'sed -i "s/'.$str1_1.'/'.$str1_2.'/g" artisan',
    ""
  );

  $str2_1 = "__DIR__.'/..";
  $str2_1 = preg_quote($str2_1, '/');
  $str2_2 = "'$app_path";
  $str2_2 = preg_quote($str2_2, '/');

  $webroot_path = $inputs['webroot_path'];
  $cmd_result = shell_write(
    $webroot_path,
    'sed -i "s/'.$str2_1.'/'.$str2_2.'/g" index.php',
    "Fix paths"
  );

  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;

}

function cmd_fix_file_permissions($result,$inputs,$form){


  $app_path = $inputs['app_path'];
  $cmd_result = shell_write(
  $app_path,
  "chown -R www-data:root $app_path;
  chmod 755 $app_path/storage",
  "Fix file permissions"
  );
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;

}

function cmd_save_db_logins($result,$inputs,$form){

  $str1_1 = "connect_to_db_name";
  $str1_1 = preg_quote($str1_1, '/');
  $str1_2 = $form["connect_to_db"]["name"];
  $str1_2 = preg_quote($str1_2, '/');

  $str2_1 = "connect_to_db_user";
  $str2_1 = preg_quote($str2_1, '/');
  $str2_2 = $form["connect_to_db"]["user"];
  $str2_2 = preg_quote($str2_2, '/');

  $str3_1 = "connect_to_db_password";
  $str3_1 = preg_quote($str3_1, '/');
  $str3_2 = $form["connect_to_db"]["password"];
  $str3_2 = preg_quote($str3_2, '/');

  $str4_1 = "connect_to_db_host";
  $str4_1 = preg_quote($str4_1, '/');
  $str4_2 = $form["connect_to_db"]["host"];
  $str4_2 = preg_quote($str4_2, '/');


  $app_path = $inputs['app_path'];
  $cmd_result = shell_write(
    $app_path,
    'cp .env.example .env;
    sed -i "s/'.$str1_1.'/'.$str1_2.'/g" .env;
    sed -i "s/'.$str2_1.'/'.$str2_2.'/g" .env;
    sed -i "s/'.$str3_1.'/'.$str3_2.'/g" .env;
    sed -i "s/'.$str4_1.'/'.$str4_2.'/g" .env',
    "Save DB logins"
  );
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;

}

function cmd_generate_key($result,$inputs,$form){

  $app_path = $inputs['app_path'];
  $cmd_result = shell_write(
  $app_path,
  "php artisan key:generate",
  "Generate key"
  );
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;

}

function cmd_download_libraries($result,$inputs,$form){

  $app_path = $inputs['app_path'];
  $cmd_result = shell_write(
    $app_path,
    "composer install",
    "Download libraries"
  );
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;

}

function cmd_populate_database($result,$inputs,$form){

  $app_path = $inputs['app_path'];
  // "php artisan migrate",
  $cmd_result = shell_write(
    $app_path,
    // "php artisan migrate",
    // "php artisan config:cache;
    // php artisan config:clear;
    // php artisan cache:clear;
    // php artisan migrate --env=production;
    // yes;",
    "php artisan migrate",
    "Populate database"
  );
  array_push($result,$cmd_result);
  if ( $cmd_result[1] == "Error") { abort_install(count($result)); }
  return $result;

}
?>
