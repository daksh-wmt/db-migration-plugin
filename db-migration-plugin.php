<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add Admin Menu
add_action('admin_menu', 'db_migrate_menu');

function db_migrate_menu() {
    add_menu_page(
        'Database Migration',
        'DB Migration',
        'manage_options',
        'db-migration',
        'db_migrate_page'
    );
}

function db_migrate_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Database Migration', 'db-migration-plugin'); ?></h1>
        <form id="db-migrate-form" class="form-group" method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="remote_host"><?php esc_html_e('Remote Database Host:', 'db-migration-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="remote_host" name="remote_host" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="remote_dbname"><?php esc_html_e('Remote Database Name:', 'db-migration-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="remote_dbname" name="remote_dbname" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="remote_username"><?php esc_html_e('Username:', 'db-migration-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="remote_username" name="remote_username" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="remote_password"><?php esc_html_e('Password:', 'db-migration-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="remote_password" name="remote_password" class="regular-text" required />
                    </td>
                </tr>
                <!-- Add URL Replacement Fields for Push/Pull -->
                <tr>
                    <th scope="row">
                        <label for="local_url"><?php esc_html_e('Local Site URL:', 'db-migration-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="local_url" name="local_url" class="regular-text" value="http://localhost" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="remote_url"><?php esc_html_e('Remote Site URL:', 'db-migration-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="remote_url" name="remote_url" class="regular-text" value="http://dev-site.com" required />
                    </td>
                </tr>
            </table>
            <button type="submit" class="button button-primary"><?php esc_html_e('Push/Pull Database', 'db-migration-plugin'); ?></button>
            <input type="hidden" id="security" name="security" value="<?php echo wp_create_nonce('db_migrate_nonce'); ?>" />
        </form>

        <div id="response" class="mt-3"></div>
    </div>
    <?php
}

function db_export_remote_database($remote_host, $remote_dbname, $remote_username, $remote_password, $local_url, $remote_url) {
    try {
        // Step 1: Connect to the remote database with a timeout
        $dsn = "mysql:host=$remote_host;dbname=$remote_dbname;charset=utf8";
        $pdo = new PDO($dsn, $remote_username, $remote_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 300 // 5-minute timeout
        ]);

        // Step 2: Get all the tables from the remote database
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        $dump_file_path = ABSPATH . 'remote_dump.sql';
        $dump_file = fopen($dump_file_path, 'w');

        // Step 3: Loop through each table and export its data
        foreach ($tables as $table) {
            // Get the create table statement
            $create_table = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            fwrite($dump_file, $create_table['Create Table'] . ";\n\n");  // Write to file
            
            // Get all the data from the table
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_values($row);
        
                $escaped_values = array_map(function($value) use ($pdo) {
                    return $pdo->quote($value);
                }, $values);
        
                fwrite($dump_file, "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $escaped_values) . ");\n");
            }
            fwrite($dump_file, "\n\n");
        }
        
        // Close the file
        fclose($dump_file);
        
        // Step 4: Read the dump file and replace the remote URL with the local URL
        $sql_dump = file_get_contents($dump_file_path);
        $sql_dump = str_replace($remote_url, $local_url, $sql_dump); // Replace domains

        // Optionally, you can overwrite the dump file with the modified SQL dump
        file_put_contents($dump_file_path, $sql_dump);

        // Return file path instead of SQL string
        return $dump_file_path;  // Return file path for future use

    } catch (PDOException $e) {
        // Handle connection or query errors
        return 'Error exporting database: ' . $e->getMessage();
    }
}






// Enqueue scripts
add_action('admin_enqueue_scripts', 'db_migrate_scripts');

function db_migrate_scripts() {
    // Enqueue custom script
    wp_enqueue_script('db-migrate-script', plugin_dir_url(__FILE__) . 'db-migrate.js', array('jquery'), null, true);

    // Localize the script with new data
    wp_localize_script('db-migrate-script', 'dbMigrate', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}

// Placeholder function to simulate local database export
function db_export_local_database() {
    global $wpdb;

    // Get all tables in the database
    $tables = $wpdb->get_col('SHOW TABLES');
    $sql_dump = '';

    foreach ($tables as $table) {
        // Add SQL for creating the table
        $create_table = $wpdb->get_row("SHOW CREATE TABLE $table", ARRAY_N);
        $sql_dump .= "\n\n" . $create_table[1] . ";\n\n";

        // Fetch all rows from the table
        $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);

        foreach ($rows as $row) {
            $sql_dump .= "INSERT INTO `$table` VALUES (";

            $values = array();
            foreach ($row as $value) {
                $values[] = (is_null($value)) ? "NULL" : "'" . esc_sql($value) . "'";
            }

            $sql_dump .= implode(", ", $values) . ");\n";
        }
    }

    return $sql_dump; // Return the dump as a string
}

function db_import_local_database($sql_dump) {
    global $wpdb;

    // Use a custom delimiter to split queries more safely
    $queries = preg_split('/;\s*[\r\n]+/', $sql_dump);

    // Prepare and run each query safely
    foreach ($queries as $query) {
        $query = trim($query); // Remove any trailing whitespace
        if (!empty($query)) {
            $wpdb->query($wpdb->prepare($query));  // Use prepared statement for security
        }
    }
}



// Register AJAX action for authenticated users
add_action('wp_ajax_db_migrate_pull', 'db_migrate_pull');

function db_migrate_pull() {
    check_ajax_referer('db_migrate_nonce', 'security');

    // Retrieve remote database details and domain replacement URLs
    $remote_host = sanitize_text_field($_POST['remote_host']);
    $remote_dbname = sanitize_text_field($_POST['remote_dbname']);
    $remote_username = sanitize_text_field($_POST['remote_username']);
    $remote_password = sanitize_text_field($_POST['remote_password']);
    $remote_url = sanitize_text_field($_POST['remote_url']);
    $local_url = sanitize_text_field($_POST['local_url']);

    try {
        // Export remote database and replace remote URL with local URL
        $remote_db_dump = db_export_remote_database($remote_host, $remote_dbname, $remote_username, $remote_password, $local_url, $remote_url);

        // Check if the dump was successful
        if (strpos($remote_db_dump, 'Error') === 0) {
            // If there's an error in exporting the remote DB
            wp_send_json_error($remote_db_dump);
        }

        // Import the modified dump into the local database
        db_import_local_database($remote_db_dump);

        // Send success response
        wp_send_json_success('Database pulled successfully with domain replacement.');
    } catch (PDOException $e) {
        error_log('Database pull failed: ' . $e->getMessage());  // Log the error
        wp_send_json_error('Database pull failed: ' . $e->getMessage());
    }

    wp_die();
}



function db_replace_domain_in_dump($db_dump, $remote_url, $local_url) {
    // Perform a regular expression replacement to safely handle URL patterns
    return preg_replace("/" . preg_quote($remote_url, '/') . "/", $local_url, $db_dump);
}




add_action('wp_ajax_db_migrate_push', 'db_migrate_push');


function db_migrate_push() {
    check_ajax_referer('db_migrate_nonce', 'security');

    // Retrieve remote database details and domain replacement URLs
    $remote_host = sanitize_text_field($_POST['remote_host']);
    $remote_dbname = sanitize_text_field($_POST['remote_dbname']);
    $remote_username = sanitize_text_field($_POST['remote_username']);
    $remote_password = sanitize_text_field($_POST['remote_password']);
    $remote_url = sanitize_text_field($_POST['remote_url']);
    $local_url = sanitize_text_field($_POST['local_url']);

    try {
        // Export local database
        $local_db_dump = db_export_local_database();

        // Replace local URL with remote URL in the dump
        $local_db_dump = db_replace_domain_in_dump($local_db_dump, $local_url, $remote_url);

        // Connect to remote database
        $dsn = "mysql:host=$remote_host;dbname=$remote_dbname;charset=utf8";
        $pdo = new PDO($dsn, $remote_username, $remote_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 300 // 5-minute timeout
        ]);

        // Execute the dump on the remote database
        $pdo->exec($local_db_dump);

        // Send success response
        wp_send_json_success('Database pushed successfully with domain replacement.');
    } catch (PDOException $e) {
        error_log('Database push failed: ' . $e->getMessage());  // Log the error
        wp_send_json_error('Database push failed: ' . $e->getMessage());
    }

    wp_die();
}


