<?php
    $conn = new mysqli("localhost","root","","unk_system2");
     
    //check connection
    if( $conn -> connect_error){
        die("connection failed:".$conn -> connect_error);
    }
?>