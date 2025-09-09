<?php
session_start();           // Iniciar sesión
session_unset();           // Limpiar todas las variables de sesión
session_destroy();         // Destruir la sesión

// Redireccionar al login
header("Location: ../index.php");
exit();
