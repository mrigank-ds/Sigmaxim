<?php

namespace Drupal\field_expression;

use Webit\Util\EvalMath\EvalMath;

/**
 * Extended EvalMath class with additional functions.
 */
class CustomEvalMath extends EvalMath {

  /**
   * Override evaluate to handle custom functions.
   */
  public function evaluate($expr) {
    if ($expr === '' || $expr === null) {
      return 0;
    }

    // Process custom functions by evaluating their contents first
    $processed = $this->processCustomFunctions($expr);
    
    // Let parent handle the rest
    return parent::evaluate($processed);
  }

  /**
   * Process custom functions in the expression.
   */
  protected function processCustomFunctions($expr) {
    $max_depth = 50;
    $depth = 0;
    
    // Keep processing until no more custom functions found
    while ($depth < $max_depth) {
      $changed = false;
      
      // time() - no arguments
      if (preg_match('/\btime\s*\(\s*\)/i', $expr)) {
        $expr = preg_replace('/\btime\s*\(\s*\)/i', time(), $expr);
        $changed = true;
      }
      
      // floor(expr) - use extractFunctionArg to handle nested parens
      if (preg_match('/\bfloor\s*\(/i', $expr, $matches, PREG_OFFSET_CAPTURE)) {
        $start = $matches[0][1] + strlen($matches[0][0]);
        $arg = $this->extractFunctionArg($expr, $start);
        if ($arg !== false) {
          $inner = $this->evaluateInner($arg);
          $result = floor($inner);
          $length = strlen('floor') + 1 + strlen($arg) + 1; // function + ( + arg + )
          $expr = substr_replace($expr, $result, $matches[0][1], $length);
          $changed = true;
        }
      }
      
      // ceil(expr)
      if (preg_match('/\bceil\s*\(/i', $expr, $matches, PREG_OFFSET_CAPTURE)) {
        $start = $matches[0][1] + strlen($matches[0][0]);
        $arg = $this->extractFunctionArg($expr, $start);
        if ($arg !== false) {
          $inner = $this->evaluateInner($arg);
          $result = ceil($inner);
          $length = strlen('ceil') + 1 + strlen($arg) + 1;
          $expr = substr_replace($expr, $result, $matches[0][1], $length);
          $changed = true;
        }
      }
      
      // round(expr) or round(expr, precision)
      if (preg_match('/\bround\s*\(/i', $expr, $matches, PREG_OFFSET_CAPTURE)) {
        $start = $matches[0][1] + strlen($matches[0][0]);
        $args = $this->extractFunctionArgs($expr, $start, 2);
        if ($args !== false) {
          $inner = $this->evaluateInner($args[0]);
          $precision = isset($args[1]) ? $this->evaluateInner($args[1]) : 0;
          $result = round($inner, $precision);
          $argStr = implode(',', $args);
          $length = strlen('round') + 1 + strlen($argStr) + 1;
          $expr = substr_replace($expr, $result, $matches[0][1], $length);
          $changed = true;
        }
      }
      
      // min(a, b)
      if (preg_match('/\bmin\s*\(/i', $expr, $matches, PREG_OFFSET_CAPTURE)) {
        $start = $matches[0][1] + strlen($matches[0][0]);
        $args = $this->extractFunctionArgs($expr, $start, 2);
        if ($args !== false && count($args) === 2) {
          $a = $this->evaluateInner($args[0]);
          $b = $this->evaluateInner($args[1]);
          $result = min($a, $b);
          $argStr = implode(',', $args);
          $length = strlen('min') + 1 + strlen($argStr) + 1;
          $expr = substr_replace($expr, $result, $matches[0][1], $length);
          $changed = true;
        }
      }
      
      // max(a, b)
      if (preg_match('/\bmax\s*\(/i', $expr, $matches, PREG_OFFSET_CAPTURE)) {
        $start = $matches[0][1] + strlen($matches[0][0]);
        $args = $this->extractFunctionArgs($expr, $start, 2);
        if ($args !== false && count($args) === 2) {
          $a = $this->evaluateInner($args[0]);
          $b = $this->evaluateInner($args[1]);
          $result = max($a, $b);
          $argStr = implode(',', $args);
          $length = strlen('max') + 1 + strlen($argStr) + 1;
          $expr = substr_replace($expr, $result, $matches[0][1], $length);
          $changed = true;
        }
      }
      
      if (!$changed) {
        break;
      }
      
      $depth++;
    }
    
    return $expr;
  }

  /**
   * Extract a single function argument handling nested parentheses.
   */
  protected function extractFunctionArg($expr, $start) {
    $level = 0;
    $arg = '';
    $len = strlen($expr);
    
    for ($i = $start; $i < $len; $i++) {
      $char = $expr[$i];
      
      if ($char === '(') {
        $level++;
        $arg .= $char;
      }
      elseif ($char === ')') {
        if ($level === 0) {
          return $arg;
        }
        $level--;
        $arg .= $char;
      }
      elseif ($char === ',' && $level === 0) {
        return $arg;
      }
      else {
        $arg .= $char;
      }
    }
    
    return false;
  }

  /**
   * Extract multiple function arguments handling nested parentheses.
   */
  protected function extractFunctionArgs($expr, $start, $maxArgs = 10) {
    $level = 0;
    $arg = '';
    $args = [];
    $len = strlen($expr);
    
    for ($i = $start; $i < $len; $i++) {
      $char = $expr[$i];
      
      if ($char === '(') {
        $level++;
        $arg .= $char;
      }
      elseif ($char === ')') {
        if ($level === 0) {
          if (trim($arg) !== '') {
            $args[] = trim($arg);
          }
          return $args;
        }
        $level--;
        $arg .= $char;
      }
      elseif ($char === ',' && $level === 0) {
        $args[] = trim($arg);
        $arg = '';
        if (count($args) >= $maxArgs) {
          break;
        }
      }
      else {
        $arg .= $char;
      }
    }
    
    return false;
  }

  /**
   * Evaluate an inner expression using parent's evaluate.
   */
  protected function evaluateInner($expr) {
    $expr = trim($expr);
    
    // If it's already a number, return it
    if (is_numeric($expr)) {
      return $expr;
    }
    
    // Otherwise evaluate it using parent (avoid recursion to our override)
    $result = parent::evaluate($expr);
    
    return $result !== false ? $result : 0;
  }

}
