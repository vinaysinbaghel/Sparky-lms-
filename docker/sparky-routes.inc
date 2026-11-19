# Block sensitive directories & internal files
location ~ ^/(tests|\.git|\.github|database|bin|scratch|app/config|app/logs|app/cache|app/courses) {
    deny all;
    return 403;
}

location ~* \.(sql|bat|sh|yml|yaml|dist|lock|log|env|ini|md)$ {
    deny all;
    return 403;
}

location ~* /(composer\.json|composer\.lock|bower\.json|web\.config|\.gitignore|\.gitattributes|\.editorconfig|\.htaccess|README\.md)$ {
    deny all;
    return 403;
}

# Prevent direct PHP execution in upload folders
location ~* ^/app/(upload|courses|cache|logs)/.*\.ph(p[34578]?|t|tml|ar)$ {
    deny all;
    return 403;
}

# Certificate clean URL
location = /certificates/ {
    rewrite ^/certificates/$ /certificates/index.php last;
}

# Course Virtual Route Rewrites
location ~* ^/courses/([^/]+)/?$ {
    rewrite ^/courses/([^/]+)/?$ /main/course_home/course_home.php?cDir=$1 last;
}

location ~* ^/courses/([^/]+)/index\.php$ {
    rewrite ^/courses/([^/]+)/index\.php$ /main/course_home/course_home.php?cDir=$1 last;
}

# SCORM content packages & assets (Protected via download_scorm handler)
location ~* ^/courses/([^/]+)/scorm/(.*)$ {
    rewrite ^/courses/([^/]+)/scorm/(.*)$ /main/document/download_scorm.php?doc_url=/$2&cDir=$1 last;
}

# Course document downloads (Protected via download handler)
location ~* ^/courses/([^/]+)/document/(.*)$ {
    rewrite ^/courses/([^/]+)/document/(.*)$ /main/document/download.php?doc_url=/$2&cDir=$1 last;
}

# Course uploads (Protected via download handler)
location ~* ^/courses/([^/]+)/upload/([^/]+)/(.*)$ {
    rewrite ^/courses/([^/]+)/upload/([^/]+)/(.*)$ /main/document/download_uploaded_files.php?code=$1&type=$2&file=$3 last;
}

# Course work & assignment submissions (Protected via work handler)
location ~* ^/courses/([^/]+)/work/(.*)$ {
    rewrite ^/courses/([^/]+)/work/(.*)$ /main/work/download.php?file=work/$2&cDir=$1 last;
}

# Course thumbnails
location ~* ^/courses/([^/]+)/course-pic85x85\.png$ {
    rewrite ^ /main/inc/ajax/course.ajax.php?a=get_course_image&code=$1&image=course_image_source last;
}

location ~* ^/courses/([^/]+)/course-pic\.png$ {
    rewrite ^ /main/inc/ajax/course.ajax.php?a=get_course_image&code=$1&image=course_image_large_source last;
}

# Any other course path: route to document download handler to enforce LMS authorization
location ~* ^/courses/([^/]+)/(.*)$ {
    rewrite ^/courses/([^/]+)/(.*)$ /main/document/download.php?doc_url=/$2&cDir=$1 last;
}

# Video & Audio Streaming with HTTP 206 Partial Content support
location ~* \.(mp4|webm|mkv|mov|ogg|mp3|wav|m4a|pdf)$ {
    expires 7d;
    add_header Cache-Control "public";
    add_header Accept-Ranges bytes;
    try_files $uri =404;
}

# Static Assets Caching
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    expires 30d;
    add_header Cache-Control "public, no-transform";
    try_files $uri =404;
}

# Application Gateway
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# PHP-FPM FastCGI Handler
location ~ \.php$ {
    try_files $uri =404;
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass app:9000;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $document_root;
    include fastcgi_params;

    fastcgi_read_timeout 300s;
    fastcgi_buffer_size 128k;
    fastcgi_buffers 256 16k;
    fastcgi_busy_buffers_size 256k;
    fastcgi_temp_file_write_size 256k;
}
