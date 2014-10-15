<?php
namespace gossi\formatter\formatters;

use gossi\formatter\token\Token;
use gossi\formatter\token\Tokenizer;
use gossi\formatter\traverse\ContextManager;

class WhitespaceFormatter extends AbstractSpecializedFormatter {
	
	private static $BLOCK_CONTEXT_MAPPING = [
		T_IF => 'ifelse',
		T_ELSEIF => 'ifelse',
		T_WHILE => 'while',
		T_FOREACH => 'foreach',
		T_FOR => 'for',
		T_CATCH => 'catch'
	];
	
	private static $SYNTAX = [
		')' => 'close',
		'(' => 'open',
		',' => 'comma', 
		';' => 'semicolon',
		':' => 'colon',
		'=>' => 'arrow',
		'->' => 'arrow', // function invocation
		'::' => 'doublecolon', // function invocation
		'?' => 'questionmark'
	];
	
	protected function doVisit(Token $token) {
		$this->applyKeywords($token);
		$this->applyAssignments($token);
		$this->applyOperators($token);
		$this->applyPrefixPostfix($token);
		$this->applyUnary($token);
		$this->applySyntax($token);		
	}
	
	private function applyKeywords(Token $token) {
		if (in_array($token->type, Tokenizer::$KEYWORDS)) {
			$this->defaultFormatter->addPostWrite(' ');
		}
	}
	
	private function applyAssignments(Token $token) {
		if (in_array($token->contents, Tokenizer::$ASSIGNMENTS)) {
			$this->whitespaceBeforeAfter($token, 'assignment', 'assignments');
		}
	}
	
	private function applyOperators(Token $token) {
		if (in_array($token->contents, Tokenizer::$OPERATORS)) {
			$this->whitespaceBeforeAfter($token, 'binary', 'operators');
		}
	}
	
	private function applyPrefixPostfix(Token $token) {
		if ($token->type == T_INC || $token->type == T_DEC) {
			// pre
			if ($this->nextToken->type == T_VAR) {
				$this->whitespaceBeforeAfter($token, 'prefix', 'operators');
			}
		
			// post
			else if ($this->prevToken->type == T_VAR) {
				$this->whitespaceBeforeAfter($token, 'postfix', 'operators');
			}
		}
	}
	

	/**
	 * @TODO
	 * @param Token $token
	 */
	private function applyUnary(Token $token) {
	
	}
	
	private function applySyntax(Token $token) {
		if (array_key_exists($token->contents, self::$SYNTAX)) {
			$key = self::$SYNTAX[$token->contents];
			$parens = $this->context->getParensContext();
			
			// return when semicolon is not inside a block context
			if ($token->contents == ';' && $parens != ContextManager::LEXICAL_BLOCK) {
				return;
			}
			
			// anyway find context and apply it
			$context = $this->findContext($token);
			$this->whitespaceBeforeAfter($token, $key, $context);
		}
	}
	
	private function findContext(Token $token) {
		$parens = $this->context->getParensContext();
		$parensToken = $this->context->getParensTokenContext();
		$context = 'default';
		
		// first check the context of the current line
		if (!empty($this->line)) {
			$context = $this->line;
		}
		
		// is it a parens group?
		else if ($parens == ContextManager::LEXICAL_GROUP) {
			$context = 'grouping';
		}
		
		// a function call?
		else if ($parens == ContextManager::LEXICAL_CALL) {
			$context = 'function_invocation';
		}
		
		// field access?
		else if ($token->contents === '->' || $token->contents === '::') {
			$context = 'field_access';
		}
		
		// or a given block statement?
		else if ($parens == ContextManager::LEXICAL_BLOCK
				&& isset(self::$BLOCK_CONTEXT_MAPPING[$parensToken->type])) {
			$context = self::$BLOCK_CONTEXT_MAPPING[$parensToken->type];
		}
		
		return $context;
	}

	private function whitespaceBeforeAfter(Token $token, $key, $context = 'default') {
		if ($this->config->getWhitespace('before_' . $key, $context)) {
			$this->defaultFormatter->addPreWrite(' ');
		}
		
		if ($this->config->getWhitespace('after_' . $key, $context)) {
			$this->defaultFormatter->addPostWrite(' ');
		}
	}
	
}
