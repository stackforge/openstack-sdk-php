<?php
/**
 * @file
 *
 * Contains exception class for ContainerNotEmptyException.
 */

namespace HPCloud\Storage\ObjectStorage;

/**
 * Indicatest that a container is not empty.
 *
 * Certain operations, notably container deletion, require that a
 * container be empty before the operation can be performed. This
 * exception is thrown when such an operation encounters an unempty
 * container when it requires an empty one.
 */
class ContainerNotEmptyException extends \HPCloud\Transport\ServerException {}
