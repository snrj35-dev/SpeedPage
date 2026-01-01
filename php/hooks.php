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
?>