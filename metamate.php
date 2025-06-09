<?php
/*
Plugin Name: MetaMate – SEO Bulk Updater for WordPress
Plugin URI: https://yourwebsite.com/alibahari
Description: Bulk update SEO meta titles and descriptions via Excel (.xlsx) for Yoast SEO and Rank Math.
Version: 2.3
Author: Ali Bahari
Author URI: https://tuxman.ir
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: metamate
Domain Path: /languages
*/


if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'simplexlsx.class.php';

function gregorian_to_jalali_str($gdate) {
    if (!$gdate) return '';
    $datetime_parts = explode(' ', $gdate);
    $date_part = $datetime_parts[0];
    $time_part = $datetime_parts[1] ?? '';

    list($gy, $gm, $gd) = array_map('intval', explode('-', $date_part));
    if ($gy == 0 || $gm == 0 || $gd == 0) return '';

    $g_days_in_month = [31,28,31,30,31,30,31,31,30,31,30,31];
    $j_days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29];

    $gy2 = $gy - 1600;
    $gm2 = $gm - 1;
    $gd2 = $gd - 1;

    $g_day_no = 365*$gy2 + floor(($gy2+3)/4) - floor(($gy2+99)/100) + floor(($gy2+399)/400);
    for ($i=0; $i < $gm2; ++$i) $g_day_no += $g_days_in_month[$i];
    if ($gm > 2 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) $g_day_no++;
    $g_day_no += $gd2;

    $j_day_no = $g_day_no - 79;

    $j_np = floor($j_day_no / 12053);
    $j_day_no %= 12053;

    $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);
    $j_day_no %= 1461;

    if ($j_day_no >= 366) {
        $jy += floor(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }

    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) {
        $j_day_no -= $j_days_in_month[$i];
    }
    $jm = $i + 1;
    $jd = $j_day_no + 1;

    return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, $time_part);
}

add_action('admin_menu', function () {
    add_menu_page('Meta-Mate', 'Meta-Mate', 'manage_options', 'Meta-Mate', 'bsu_upload_page');
});

function bsu_upload_page() {
    ?>
    <div class="wrap" style="max-width: 800px; margin: 50px auto; font-family: vazirmatn; background:#fff; padding: 40px; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.05); font-family: 'Vazirmatn', sans-serif;">
        <h1 style="font-family: vazirmatn , sans-serif , 'Courier New', Courier, monospace;">بروزرسانی گروهی سئو (MetaMate)</h1>

        <div style="background: #f9f9f9; padding: 20px; border-radius: 10px; border: 1px solid #ddd; margin-bottom: 30px;">
            <h2 style="margin-top:0; font-family: vazirmatn;">راهنمای استفاده از افزونه</h2>
            <ol style="font-size: 15px; line-height: 1.8;">
                <li>یک فایل اکسل (xlsx) بسازید با سه ستون: <strong>URL</strong>، <strong>عنوان جدید</strong>، <strong>توضیحات جدید</strong>.</li>
                <li>مطمئن شوید که لینک‌ها مربوط به نوشته‌های وردپرس شما باشند.</li>
                <li>فایل را از طریق دکمه زیر انتخاب کرده و ارسال کنید.</li>
                <li>تغییرات در متای Yoast SEO یا Rank Math اعمال می‌شوند و لاگ در پایین قابل مشاهده است.</li>
            </ol>
        </div>

       <form id="bsu-upload-form" method="post" enctype="multipart/form-data">
    <label for="seo_file" class="bsu-file-label" style="display:inline-block; padding:12px 28px; background: linear-gradient(45deg, #3498db, #2980b9); color:#fff; border-radius:8px; cursor:pointer; font-size:16px; font-weight:600; user-select:none;">
        انتخاب فایل اکسل (xlsx)
    </label>
    <input type="file" name="seo_file" id="seo_file" accept=".xlsx" required style="display:none;">
    <span id="file-selected-msg" style="display:block; margin-top:10px; font-size:14px; color:green;"></span>
    <br><br>
    <input type="submit" name="upload" class="button-primary bsu-submit-btn" value="آپلود و اجرا" style="font-family: 'Vazirmatn', sans-serif; font-size:16px; padding: 12px 28px; border-radius:8px; cursor:pointer; border:none; background: linear-gradient(45deg, #3498db, #2980b9); color:#fff;">
</form>

<div id="bsu-loader" style="display:none; text-align: center; margin-top: 20px;">
   <img src="/img/loding.gif" width="50" alt="در حال پردازش..." />
   <p>در حال پردازش فایل، لطفاً صبر کنید...</p>
</div>
<!-- نوار پیشرفت -->
<div id="bsu-progress-container" style="display:none; margin-top: 20px; background: #eee; border-radius: 6px; width: 100%; height: 20px;">
    <div id="bsu-progress-bar" style="height: 100%; width: 0%; background: linear-gradient(45deg, #3498db, #2980b9); border-radius: 6px;"></div>
</div>
<!-- محل نمایش نتیجه آپلود -->
<div id="bsu-upload-result" style="margin-top:20px;"></div>
        <?php bsu_handle_upload(); ?>
        <hr style="margin: 40px 0;">
        <div id="bsu-log-section">
            <h2 style="font-family: vazirmatn , sans-serif;">لاگ‌های ثبت‌شده</h2>
            <?php
            $log_file_path = plugin_dir_path(__FILE__) . 'bsu-log.csv';
            if (file_exists($log_file_path)) {
                $lines = array_map('str_getcsv', file($log_file_path));
                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                foreach ($lines[0] as $header) {
                    echo '<th>' . esc_html($header) . '</th>';
                }
                echo '</tr></thead><tbody>';
                foreach (array_slice($lines, 1) as $line) {
                    echo '<tr>';
                    foreach ($line as $key => $col) {
                        if ($key === 0) {
                            $safe_url = esc_url($col);
                            echo '<td style="text-align:center;">';
                            echo '<button class="bsu-toggle-btn" style="background:#2980b9; color:#fff; border:none; padding:5px 12px; border-radius:6px; cursor:pointer;">نمایش آدرس</button>';
                            echo '<div class="bsu-url-box" style="display:none; margin-top:6px; font-size:12px; direction:ltr; color:#555;">' . $safe_url . '</div>';
                            echo '</td>';
                        } elseif ($key === 7) {
                            echo '<td>' . esc_html(gregorian_to_jalali_str($col)) . '</td>';
                        } else {
                            echo '<td>' . esc_html($col) . '</td>';
                        }
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>هیچ لاگی یافت نشد.</p>';
            }
            ?>
        </div>
        <p style="margin-top: 30px; text-align: center; font-size: 14px; color: #999;">
            طراحی شده توسط علی بهاری | نسخه ۲.۳
        </p>
    </div>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn&display=swap" rel="stylesheet">
    <script>
        document.getElementById('bsu-upload-form').addEventListener('submit', function () {
            document.getElementById('bsu-loader').style.display = 'block';
        });
        document.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('bsu-toggle-btn')) {
                const urlBox = e.target.nextElementSibling;
                if (urlBox.style.display === 'none' || urlBox.style.display === '') {
                    urlBox.style.display = 'block';
                    e.target.textContent = 'پنهان کردن آدرس';
                } else {
                    urlBox.style.display = 'none';
                    e.target.textContent = 'نمایش آدرس';
                }
            }
        });
        document.getElementById('seo_file').addEventListener('change', function () {
            const label = document.getElementById('file-selected-msg');
            if (this.files.length > 0) {
                label.textContent = 'فایل با موفقیت انتخاب شد: ' + this.files[0].name;
            } else {
                label.textContent = '';
            }
        });
    </script>
    <style>
        body, input, button, select {
            font-family: 'Vazirmatn', sans-serif !important;
        }
        .widefat {
            margin-top: 20px;
            border-collapse: collapse;
            width: 100%;
        }
        table.widefat th, table.widefat td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        table.widefat th {
            background-color: #f4f6f8;
            color: #2c3e50;
        }
        .bsu-toggle-btn:hover {
            background-color: #3498db !important;
        }
        .updated, .error {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
        }
        .updated {
            background: #e6f7e6;
            border-left: 4px solid #46b450;
        }
        .error {
            background: #fdecea;
            border-left: 4px solid #dc3232;
        }
    </style>
    <?php
}
function bsu_handle_upload() {
    if (!isset($_POST['upload']) || !isset($_FILES['seo_file'])) return;

    if ($_FILES['seo_file']['error'] !== UPLOAD_ERR_OK) {
        echo "<div class='error'><p>خطا در آپلود فایل.</p></div>";
        return;
    }
    $file_path = $_FILES['seo_file']['tmp_name'];
    $xlsx = new SimpleXLSX($file_path);
    $rows = $xlsx->rows();
    $log = [["URL", "Type", "Old Title", "New Title", "Old Description", "New Description", "Status", "Time"]];
    foreach ($rows as $i => $row) {
        if ($i == 0) continue;
        list($url, $new_title, $new_desc) = array_pad($row, 3, null);
        $url = filter_var(trim($url), FILTER_SANITIZE_URL);
        $new_title = sanitize_text_field(trim($new_title ?? ''));
        $new_desc = sanitize_textarea_field(trim($new_desc ?? ''));
        if (empty($url)) {
            $log[] = ["Empty URL", "N/A", "N/A", $new_title, "N/A", $new_desc, "Error: URL is empty", current_time('mysql')];
            continue;
        }
        $post_id = url_to_postid($url);
        $status = "Fail";
        $type = "Unknown";
        $old_title = "";
        $old_desc = "";
        if ($post_id) {
            $post = get_post($post_id);
            $type = $post->post_type;
            $seo_plugin_updated = false;
            if (defined('WPSEO_VERSION')) {
                $old_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
                $old_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                update_post_meta($post_id, '_yoast_wpseo_title', $new_title);
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $new_desc);
                $status = "Success (Yoast)";
                $seo_plugin_updated = true;
            }
            if (defined('RANK_MATH_VERSION')) {
                if (!$seo_plugin_updated) {
                    $old_title = get_post_meta($post_id, 'rank_math_title', true);
                    $old_desc = get_post_meta($post_id, 'rank_math_description', true);
                }
                update_post_meta($post_id, 'rank_math_title', $new_title);
                update_post_meta($post_id, 'rank_math_description', $new_desc);
                $status = $seo_plugin_updated ? "Success (Yoast & RankMath)" : "Success (RankMath)";
            }

            if (!$seo_plugin_updated && !defined('RANK_MATH_VERSION')) {
                $status = "No SEO plugin found";
            }
        } else {
            $status = "Post not found";
        }
        $log[] = [$url, $type, $old_title, $new_title, $old_desc, $new_desc, $status, current_time('mysql')];
    }
    $log_file_path = plugin_dir_path(__FILE__) . 'bsu-log.csv';
    $fp = @fopen($log_file_path, 'w');
    if ($fp !== false) {
        fwrite($fp, "\xEF\xBB\xBF");
        foreach ($log as $line) {
            fputcsv($fp, $line);
        }
        fclose($fp);
        echo "<div class='updated'><p>فرآیند آپدیت با موفقیت انجام شد.</p></div>";
    } else {
        echo "<div class='error'><p>خطا در ذخیره لاگ.</p></div>";
    }
}
?>
<script>
document.getElementById('seo_file').addEventListener('change', function () {
    const label = document.getElementById('file-selected-msg');
    if (this.files.length > 0) {
        label.textContent = 'فایل با موفقیت انتخاب شد: ' + this.files[0].name;
    } else {
        label.textContent = '';
    }
});
document.getElementById('bsu-upload-form').addEventListener('submit', function(e) {
    e.preventDefault(); // جلوگیری از ارسال فرم به صورت معمول
    const form = e.target;
    const fileInput = document.getElementById('seo_file');
    if (fileInput.files.length === 0) {
        alert('لطفاً فایل اکسل را انتخاب کنید.');
        return;
    }
    const formData = new FormData(form);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '', true); // ارسال به همان صفحه (صفحه مدیریت)
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const percent = (e.loaded / e.total) * 100;
            const progressBar = document.getElementById('bsu-progress-bar');
            const progressContainer = document.getElementById('bsu-progress-container');
            progressContainer.style.display = 'block';
            progressBar.style.width = percent + '%';
        }
    };
    xhr.onloadstart = function() {
        document.getElementById('bsu-loader').style.display = 'block';
        document.getElementById('bsu-upload-result').innerHTML = '';
        document.getElementById('bsu-progress-container').style.display = 'block';
        document.getElementById('bsu-progress-bar').style.width = '0%';
    };
    xhr.onload = function() {
        document.getElementById('bsu-loader').style.display = 'none';

        if (xhr.status >= 200 && xhr.status < 300) {
            // قرار دادن نتیجه در div مخصوص نمایش نتیجه
            document.getElementById('bsu-upload-result').innerHTML = xhr.responseText;
            document.getElementById('bsu-progress-bar').style.width = '100%';
        } else {
            document.getElementById('bsu-upload-result').innerHTML = '<div class="error"><p>خطا در آپلود فایل.</p></div>';
        }
    };
    xhr.onerror = function() {
        document.getElementById('bsu-loader').style.display = 'none';
        document.getElementById('bsu-upload-result').innerHTML = '<div class="error"><p>خطا در آپلود فایل.</p></div>';
    };
    xhr.send(formData);
});
</script>
