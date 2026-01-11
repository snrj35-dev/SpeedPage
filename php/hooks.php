<?php
// Global Hooks System

$hooks = [];

/**
 * Add a new hook listener
 *
 * @param string   $tag       Hook location/name (e.g. 'head_start')
 * @param callable $callback  Function to call
 * @param int      $priority  Execution order (lower runs first)
 */
function add_hook($tag, $callback, $priority = 10)
{
    global $hooks;
    $hooks[$tag][] = [
        'callback' => $callback,
        'priority' => $priority
    ];
}

/**
 * Run all hooks listeners for a tag
 *
 * @param string $tag   Hook location/name
 * @param mixed  $data  Optional data to pass to the callback
 */
function run_hook($tag, $data = null)
{
    global $hooks;
    if (isset($hooks[$tag])) {
        // Sort by priority
        usort($hooks[$tag], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        foreach ($hooks[$tag] as $hook) {
            if (is_callable($hook['callback'])) {
                // Return value of callback is used as data for the next hook
                $result = call_user_func($hook['callback'], $data);
                if ($result !== null) {
                    $data = $result;
                }
            }
        }
    }
    return $data;
}

// AI Bug Reporter Hook
add_hook('footer_end', function () {
    // Sadece adminler görebilir
    if ((session_status() === PHP_SESSION_NONE ? session_start() : true) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        echo <<<HTML
    <!-- AI Bug Reporter -->
    <div id="ai-bug-reporter-btn" title="AI Asistan ile Hatayı Analiz Et" style="position:fixed; bottom:20px; right:20px; z-index:99999; cursor:pointer; background:#dc3545; color:white; width: 50px; height: 50px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 15px rgba(220,53,69,0.4); transition: transform 0.2s;">
        <i class="fas fa-bug" style="font-size: 20px;"></i>
    </div>
    <script>
        (function(){
            // Console Error Yakalayıcı
            if (!window.aiConsoleErrors) window.aiConsoleErrors = [];
            const originalConsoleError = console.error;
            console.error = function(...args) {
                window.aiConsoleErrors.push(args.join(' '));
                if(originalConsoleError) originalConsoleError.apply(console, args);
            };

            const btn = document.getElementById('ai-bug-reporter-btn');
            if(btn){
                btn.addEventListener('mouseenter', () => btn.style.transform = 'scale(1.1)');
                btn.addEventListener('mouseleave', () => btn.style.transform = 'scale(1)');
                
                btn.addEventListener('click', function() {
                    const report = {
                        url: window.location.href,
                        html: document.body.innerText.substring(0, 8000), // İçerik özeti
                        errors: window.aiConsoleErrors,
                        timestamp: new Date().toISOString()
                    };
                    
                    try {
                        localStorage.setItem('ai_bug_report', JSON.stringify(report));
                        
                        if(confirm('Sayfa verileri ve hatalar yakalandı. AI Paneli açılarak analiz başlatılsın mı?')) {
                             // Admin paneli yolunu dinamik belirlemeye çalışalım veya standart yolu kullanalım
                             const adminPath = '/admin/index.php?page=aipanel&auto_analyze=1';
                             window.location.href = adminPath;
                        }
                    } catch(e) {
                        alert('Rapor oluşturulurken hata: ' + e.message);
                    }
                });
            }
        })();
    </script>
HTML;
    }
});
?>