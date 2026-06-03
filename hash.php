<?php
/**
 * hash.php - Administrator Credential Generator
 * Purpose: Generate secure hashes for the bh.users table.
 */

// --- CONFIGURATION ---
$name     = "web.Cris";
$email    = "web.Cris@gmail.com";
$password = "CrisAdmin"; // The plain text password to hash
$phone    = "09633951825";
$role     = "admin";

// --- GENERATION ---
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// --- OUTPUT ---
header('Content-Type: text/plain');
echo "--- NEW ADMIN DETAILS ---\n";
echo "Name:     $name\n";
echo "Email:    $email\n";
echo "Password: $password\n";
echo "Role:     $role\n";
echo "--------------------------\n";
echo "READY-TO-USE SQL:\n\n";

echo "INSERT INTO bh.users (name, email, password, phone, role) \n";
echo "VALUES (\n";
echo "    '$name', \n";
echo "    '$email', \n";
echo "    '$hashedPassword', \n";
echo "    '$phone', \n";
echo "    '$role'\n";
echo ");";
?>