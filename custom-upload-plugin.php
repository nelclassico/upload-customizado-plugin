<?php
/*
Plugin Name: Plugin Upload Customizado
Description: O Upload Customizado cria um formulário através de um shortcode para inserção de arquivos dentro de pastas na pasta uploads
do servidor, para que os arquivos sejam enviados, o servidor precisa estar com permissão para o usuário. O Upload Customizado também gera através de outro shortcode uma listagem da pasta criada folders/subfolders.
Version: 1.0
Author: Emanoel de Oliveira
Icon: dashicons-cloud-upload
*/


// Adicione esta função ao arquivo custom-upload-plugin.php
function custom_upload_plugin_enqueue_assets() {
    $plugin_url = plugin_dir_url(__FILE__);

    // Registra e enfileira o CSS do Bootstrap
    wp_register_style('bootstrap-css', $plugin_url . 'assets/bootstrap/css/bootstrap.min.css');
    wp_enqueue_style('bootstrap-css');

    // Registra e enfileira o JS do Bootstrap
    wp_register_script('bootstrap-js', $plugin_url . 'assets/bootstrap/js/bootstrap.min.js', array('jquery'), null, true);
    wp_enqueue_script('bootstrap-js');
}

add_action('admin_enqueue_scripts', 'custom_upload_plugin_enqueue_assets');



// Adicione aqui as funções listar_arquivos_subpastas() e list_folders_recursive()
// Função para listar as pastas de forma recursiva
function list_folders_recursive( $path, $relative_path = '', $indent = '' ) {
    $options = array();

    $folders = array_diff( scandir( $path ), array( '..', '.' ) );

    foreach ( $folders as $folder_name ) {
        if ( is_dir( $path . '/' . $folder_name ) ) {
            $folder_path = $relative_path . '/' . $folder_name;
            $options[] = array(
                'value' => $folder_path,
                'label' => $indent . $folder_name
            );
            $sub_options = list_folders_recursive( $path . '/' . $folder_name, $folder_path, $indent . '&nbsp;&nbsp;&nbsp;' );
            $options = array_merge( $options, $sub_options );
        }
    }

    return $options;
}

// Função para criar o formulário de upload de arquivos
function custom_upload_form_shortcode() {
    ob_start();

    // Verifica se o formulário foi submetido
    if ( isset( $_POST['custom_upload_submit'] ) ) {
        $folder = sanitize_text_field( $_POST['custom_upload_folder'] );
        $new_folder = sanitize_text_field( $_POST['custom_upload_folder_new'] );

        // Remove barras no início e no final do nome da pasta
        $folder = trim( $folder, '/' );
        $new_folder = trim( $new_folder, '/' );

        // Verifica se foi digitado o nome de uma nova pasta
        if ( ! empty( $new_folder ) ) {
            // Define o caminho completo da nova pasta
            $upload_dir = WP_CONTENT_DIR . '/uploads/' . $new_folder;

            // Verifica se a pasta já existe
            if ( ! file_exists( $upload_dir ) ) {
                if ( wp_mkdir_p( $upload_dir ) ) {
                    echo '<p class="alert alert-success">Nova pasta criada em: ' . $upload_dir . '</p>';
                } else {
                    echo '<p class="alert alert-danger">Falha ao criar a pasta.</p>';
                    return;
                }
            } else {
                echo '<p class="alert alert-warning">Pasta já existente. O arquivo será enviado para a pasta selecionada.</p>';
            }
        } else {
            // A pasta escolhida no <select> é uma pasta existente
            $upload_dir_base = WP_CONTENT_DIR . '/uploads/';
            if ( strpos( $folder, $upload_dir_base ) === false ) {
                $folder = ltrim( $folder, '/' ); // Remove barra inicial, se houver
                $folder = $upload_dir_base . $folder;
            }
            $upload_dir = $folder;
        }

        // Lida com o upload dos arquivos
        if ( ! empty( $_FILES['custom_upload_file'] ) ) {
            $file_names = $_FILES['custom_upload_file']['name'];
            $file_temps = $_FILES['custom_upload_file']['tmp_name'];

            for ( $i = 0; $i < count( $file_names ); $i++ ) {
                $file_name = $file_names[$i];
                $file_temp = $file_temps[$i];

                // Move o arquivo para a pasta escolhida
                $uploaded = move_uploaded_file( $file_temp, $upload_dir . '/' . $file_name );

                if ( $uploaded ) {
                    echo '<p class="alert alert-success">Arquivo enviado com sucesso para a pasta: ' . $folder . '</p>';
                } else {
                    echo '<p class="alert alert-danger">Falha ao enviar o arquivo ' . $file_name . '.</p>';

                    // Adicione o trecho de depuração aqui
                    echo '<p>Debug: Caminho da pasta de destino: ' . $upload_dir . '</p>';
                }

                // Adiciona o arquivo à biblioteca de mídias do WordPress
                $attachment = array(
                    'guid'           => $upload_dir . '/' . $file_name,
                    'post_mime_type' => $_FILES['custom_upload_file']['type'][$i],
                    'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_name ),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );
                $attachment_id = wp_insert_attachment( $attachment, $upload_dir . '/' . $file_name );

                // Gera os metadados do arquivo e atualiza o post de anexo para criar versões em miniatura, etc.
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_dir . '/' . $file_name );
                wp_update_attachment_metadata( $attachment_id, $attachment_data );
            }
        }
    }

    ?>
    <form class="needs-validation" method="post" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
            <label for="custom_upload_file" class="form-label">Escolha o(s) arquivo(s) para enviar:</label>
            <input type="file" name="custom_upload_file[]" id="custom_upload_file" class="form-control" required multiple>
            <div class="invalid-feedback">Selecione um ou mais arquivos para enviar.</div>
        </div>
        <div class="mb-3">
            <label for="custom_upload_folder" class="form-label">Escolha a pasta existente ou digite o nome da nova pasta:</label>
            <select name="custom_upload_folder" id="custom_upload_folder" class="form-select">
                <option value="">Selecione uma pasta</option>
                <?php
                $upload_path = WP_CONTENT_DIR . '/uploads';
                $folder_options = list_folders_recursive( $upload_path );
                foreach ( $folder_options as $option ) {
                    echo '<option value="' . esc_attr( $option['value'] ) . '">' . $option['label'] . '</option>';
                }
                ?>
            </select>
            <input type="text" name="custom_upload_folder_new" id="custom_upload_folder_new" class="form-control mt-2" placeholder="Digite o nome da nova pasta">
        </div>

        <button type="submit" name="custom_upload_submit" class="btn btn-primary">Enviar</button>
    </form>
    <script>
        // Adicionar validação Bootstrap ao formulário
        (function () {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode( 'custom_upload_form', 'custom_upload_form_shortcode' );

/* Modelo  [custom_upload_form] */ 


function listar_arquivos_subpastas($folder_path, $parent_title = '', $is_active = false) {
    $output = '';

    $files = scandir($folder_path);

    usort($files, function ($a, $b) {
        $a = pathinfo($a, PATHINFO_FILENAME);
        $b = pathinfo($b, PATHINFO_FILENAME);

        preg_match('/\d+/', $a, $matches_a);
        preg_match('/\d+/', $b, $matches_b);

        if (!empty($matches_a) && !empty($matches_b)) {
            $num_a = intval($matches_a[0]);
            $num_b = intval($matches_b[0]);

            return $num_a < $num_b ? 1 : -1;
        } else {
            return strcmp($a, $b);
        }
    });

    $last_folder = $is_first; // Variável para armazenar o nome da última pasta adicionada

    foreach ($files as $file) {
        if ('.' !== $file && '..' !== $file) {
            $file_path = $folder_path . '/' . $file;

            if (is_dir($file_path)) {
                $subfolder_title = $parent_title !== '' ? $parent_title . '/' . $file : $file;
                $output .= '<div class="accordion-item">';
                $output .= '<h2 class="accordion-header">';
                $output .= '<button class="accordion-button ' . ($is_active ? 'active' : '') . '" type="button" data-bs-toggle="collapse" data-bs-target="#collapse' . esc_attr(sanitize_title($subfolder_title)) . '">';
                $output .= '<i class="fa fa-folder" aria-hidden="true"></i>&nbsp; ' . esc_html($file);
                $output .= '</button>';
                $output .= '</h2>';
                $output .= '<div id="collapse' . esc_attr(sanitize_title($subfolder_title)) . '" class="accordion-collapse collapse">';
                $output .= '<div class="accordion-body">';
                $output .= listar_arquivos_subpastas($file_path, $subfolder_title);
                $output .= '</div>';
                $output .= '</div>';
                $output .= '</div>';
            } else {
                $file_url = wp_upload_dir()['baseurl'] . str_replace(wp_upload_dir()['basedir'], '', $file_path);
                $output .= '<p><a href="' . esc_url($file_url) . '" download>' . esc_html($file) . '</a></p>';
            }
        }
    }

    return $output;
}


// Função para exibir a página de documentação
function custom_upload_plugin_docs_page() {
    ?>
    <div class="wrap">
        <h1>Custom Upload Plugin Documentation</h1>
        <p>Use the following shortcode to display the upload form:</p>
        <pre>[custom_upload_form]</pre>
        
        <p>Use the following shortcode to display the list of files and folders:</p>
        <pre>[custom_list_folders]</pre>
        <p>To access subfolders: [custom_list_folders="folder1/subfolder1"]</p>

        <p>Folder structure example:</p>
        <pre>
        uploads/
        ├── folder1/
        │   ├── subfolder1/
        │   └── subfolder2/
        ├── folder2/
        └── ...
        </pre>

        <p>Instructions on how to use the plugin:</p>
        <ul>
            <li>Upload files using the form above.</li>
            <li>Select an existing folder or create a new folder.</li>
            <li>Uploaded files will be added to the selected folder.</li>
            <li>Use the shortcode to display the upload form on any page or post.</li>
            <li>Use the shortcode to display the list of files and folders on any page or post.</li>
        </ul>
    </div>
    <?php
}


// Adicione esta função ao arquivo custom-upload-plugin.php
function custom_upload_plugin_menu() {
    add_menu_page(
        'Custom Upload Plugin Documentation',
        'Upload Plugin Docs',
        'manage_options',
        'custom-upload-plugin-docs',
        'custom_upload_plugin_docs_page',
        'dashicons-cloud-upload', // Ícone para o menu
        80 // Posição no menu
    );
}

// Registre o menu no hook admin_menu
add_action('admin_menu', 'custom_upload_plugin_menu');
