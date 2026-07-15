<?php
require_once __DIR__ . '/../config.php';
requireLogin();
header('Location: doctors.php');
exit;
