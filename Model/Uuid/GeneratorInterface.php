<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Model\Uuid;

/**
 * Generates and validates canonical customer UUID values.
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
interface GeneratorInterface
{
    /**
     * Generate a lowercase canonical UUID string.
     *
     * @return string UUID in `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` format.
     */
    public function generate(): string;

    /**
     * Validate a canonical lowercase UUID string.
     *
     * @param string $uuid Candidate UUID value.
     * @return bool True when the value is a valid canonical UUID string.
     */
    public function isValid(string $uuid): bool;
}
