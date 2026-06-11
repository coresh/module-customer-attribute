<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Model\Uuid;

use Magento\Framework\Exception\LocalizedException;

/**
 * Cryptographically safe UUID v4 generator.
 *
 * Purpose:
 * - Produces non-sequential customer identifiers that do not expose internal customer IDs.
 * - Uses PHP `random_bytes()` for cryptographically secure randomness.
 *
 * Business rule:
 * - UUID v4 is selected because the assessment requires globally unique identifiers and does
 *   not require time ordering. UUID v7 can be considered later if sortable identifiers become
 *   a business requirement.
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class Generator implements GeneratorInterface
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /**
     * Generate a lowercase canonical UUID v4 string.
     *
     * @return string UUID in canonical lowercase format.
     * @throws LocalizedException When secure random bytes cannot be generated.
     */
    public function generate(): string
    {
        try {
            $bytes = random_bytes(16);
        } catch (\Throwable $exception) {
            throw new LocalizedException(__('Unable to generate a secure customer UUID.'), $exception);
        }

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Validate a canonical UUID string.
     *
     * @param string $uuid Candidate UUID value.
     * @return bool True when the UUID uses canonical lowercase syntax.
     */
    public function isValid(string $uuid): bool
    {
        return preg_match(self::UUID_PATTERN, $uuid) === 1;
    }
}
