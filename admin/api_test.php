// <?php
// include_once '../dbconfig.php';
// include_once '../inc/function-quiz.php';
// include_once '../inc/function.php';

// // 1. Call the function and save its output into a variable
// $testGroups = getPackageGroups();

// // 2. Check if data was successfully fetched
// if ($testGroups !== null) {
//     echo "<h3> API Connection Successful! Found " . count($testGroups) . " packages:</h3>";
    
//     echo "<pre>";
//     print_r($testGroups); // This will print the raw database rows beautifully on screen
//     echo "</pre>";
    
// } else {
//     echo "<h3>API Error:</h3>";
//     echo "Could not fetch data. This could mean:<br>";
//     echo "* The URL or API Key inside getPackageGroups() is incorrect.<br>";
//     echo "* The API server returned a error (e.g., 401 Unauthorized or 500 Server Error).<br>";
//     echo "* cURL is disabled or blocking the request.";
// }

?>