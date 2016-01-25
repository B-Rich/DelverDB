<?php
include_once "users.php";

global $IsLoggedIn, $LoginErrorMessage;

require_once 'C:/pear/pear/twig/autoloader.php';
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

////////////////////////////////////////////////////////////////////////////////
/// HEADER

$template = $twig->loadTemplate('header.twig');
$args = array();
$args["title"] = "Home - DDB";
$args["heading"] = "Home";
$args['isloggedin'] = $IsLoggedIn;

if($LoginErrorMessage != null)
    $args['loginerrormessage'] = $LoginErrorMessage;
$args['loginurl'] = $_SERVER['PHP_SELF'];
if($IsLoggedIn)
{
    $args['username'] = $_SESSION['username'];
}
echo $template->render($args);

////////////////////////////////////////////////////////////////////////////////
/// TASKS

require_once "C:\pear\pear\propel\Propel.php";
Propel::init("\propel\build\conf\magic_db-conf.php");
set_include_path("propel/build/classes/" . PATH_SEPARATOR . get_include_path());

?>

<h2>Add New Task</h2>
<form action='index.php' method='get' >
<input type='text' name='tasktext' />
<input type='submit' value='submit' />
</form>
<h2>Tasks</h2>
<?php 

if(array_key_exists('tasktext', $_GET))
{
    $task = new Task();
    $task->setDescription($_GET['tasktext']);
    $task->save();
}
else if(array_key_exists('toggle', $_GET))
{
    $FetchQuery = new TaskQuery();
    $Result = $FetchQuery->findPK($_GET['toggle']);
    
    if($Result)
    {
        $Result->setCompleted(!$Result->GetCompleted());
        $Result->save();
    }    
}

$count = 0;

$TaskQuery = new TaskQuery();
$tasks = $TaskQuery->find();

echo "<table>";
foreach($tasks as $task)
{
    $count ++;
    echo "<tr>";
    echo "<td>$count</td>";
    echo "<td>".$task->GetDescription()."</td>";
    echo "<td>";
    echo "<a href=\"index.php?toggle={$task->GetId()}\">";
    echo "<img src=\"images/".($task->GetCompleted() == 0 ? "cross-24.png" : "tick-24.png")."\" alt=\"".($task->GetCompleted() ? "Not Done" : "Done")."\"/>";
    echo "</a></td>";
    echo "</tr>";
}
echo "</table>";

////////////////////////////////////////////////////////////////////////////////
/// FOOTER
$template = $twig->loadTemplate('footer.twig');
echo $template->render(array());

?>