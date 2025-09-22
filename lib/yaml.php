<?php
/**
 * 简单的YAML处理库，不依赖Symfony Yaml组件
 */

/**
 * 解析YAML字符串为PHP数组（全局函数）
 * 
 * @param string $input YAML字符串
 * @return array 解析结果
 */
function orrism_yaml_parse($input) {
    // 基本YAML解析，仅支持简单结构
    $result = [];
    $lines = explode("\n", $input);
    $currentMap = &$result;
    $path = [];
    $currentIndent = 0;
    
    foreach ($lines as $line) {
        // 跳过注释和空行
        if (empty(trim($line)) || substr(trim($line), 0, 1) === '#') {
            continue;
        }
        
        // 计算缩进
        $indent = strlen($line) - strlen(ltrim($line));
        $line = trim($line);
        
        // 处理YAML映射（键值对）
        if (strpos($line, ':') !== false) {
            list($key, $value) = array_map('trim', explode(':', $line, 2));
            
            // 如果缩进减少，回到上一级
            if ($indent < $currentIndent) {
                $steps = ($currentIndent - $indent) / 2;
                for ($i = 0; $i < $steps; $i++) {
                    array_pop($path);
                }
                $currentMap = &$result;
                foreach ($path as $p) {
                    $currentMap = &$currentMap[$p];
                }
            }
            
            // 如果值为空，可能是嵌套映射的开始
            if (empty($value)) {
                $currentMap[$key] = [];
                $path[] = $key;
                $currentMap = &$currentMap[$key];
            } else {
                // 尝试转换为合适的PHP类型
                if (is_numeric($value)) {
                    $value = $value + 0; // 转为数字
                } elseif ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif ($value === 'null') {
                    $value = null;
                }
                $currentMap[$key] = $value;
            }
            
            $currentIndent = $indent;
        }
        // 处理序列
        elseif (strpos($line, '-') === 0) {
            $value = trim(substr($line, 1));
            if (is_numeric($value)) {
                $value = $value + 0;
            } elseif ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            } elseif ($value === 'null') {
                $value = null;
            }
            $currentMap[] = $value;
        }
    }
    
    return $result;
}

/**
 * 将PHP数组转为YAML字符串（全局函数）
 * 
 * @param array $array PHP数组
 * @return string YAML字符串
 */
function orrism_yaml_dump($array) {
    return _orrism_yaml_dump_array($array);
}

/**
 * 递归转换数组为YAML（内部辅助函数）
 */
function _orrism_yaml_dump_array($array, $indent = 0) {
    $result = '';
    $space = str_repeat(' ', $indent);
    
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            if (_orrism_yaml_is_sequential_array($value)) {
                $result .= "{$space}{$key}:\n";
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $result .= "{$space}  -\n" . _orrism_yaml_dump_array($item, $indent + 4);
                    } else {
                        $result .= "{$space}  - " . _orrism_yaml_dump_scalar($item) . "\n";
                    }
                }
            } else {
                $result .= "{$space}{$key}:\n" . _orrism_yaml_dump_array($value, $indent + 2);
            }
        } else {
            $result .= "{$space}{$key}: " . _orrism_yaml_dump_scalar($value) . "\n";
        }
    }
    
    return $result;
}

/**
 * 检查是否为索引数组（内部辅助函数）
 */
function _orrism_yaml_is_sequential_array($array) {
    return array_keys($array) === range(0, count($array) - 1);
}

/**
 * 转换标量值为YAML格式（内部辅助函数）
 */
function _orrism_yaml_dump_scalar($value) {
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    } elseif (is_null($value)) {
        return 'null';
    } elseif (is_string($value) && (strpos($value, "\n") !== false || strpos($value, ":") !== false)) {
        return "\"" . str_replace("\"", "\\\"", $value) . "\"";
    }
    return $value;
} 