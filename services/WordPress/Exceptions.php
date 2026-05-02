<?php
/**
 * WordPress Integration Exceptions
 */

class WordPressException extends Exception {}

class WordPressNetworkException extends WordPressException {}

class WordPressTimeoutException extends WordPressException {}

class WordPressAuthenticationException extends WordPressException {}

class WordPressForbiddenException extends WordPressException {}

class WordPressInvalidResponseException extends WordPressException {}

class WordPressUnreachableException extends WordPressException {}

class WordPressConfigurationException extends WordPressException {}
