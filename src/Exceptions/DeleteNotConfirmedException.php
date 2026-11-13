<?php

declare(strict_types=1);

namespace Breezedoc\Exceptions;

/**
 * A delete was sent, but a follow-up check still finds the document.
 *
 * Thrown by {@see \Breezedoc\Api\Documents::deleteDocument()} when the REST API still
 * returns the document after the website delete, and by
 * {@see \Breezedoc\Web\WebSession::deleteDocument()} when the website still shows it.
 */
class DeleteNotConfirmedException extends BreezedocException
{
}
