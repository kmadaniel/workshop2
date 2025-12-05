<?php
if (extension_loaded('pdo_pgsql')) {
    echo "✅ PDO_PGSQL loaded!";
} else {
    echo "❌ PDO_PGSQL NOT loaded!";
}
?>
