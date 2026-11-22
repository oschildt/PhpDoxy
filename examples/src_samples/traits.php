<?php
/**
 * This is a file for demonstration of the traits.
 *
 * @author Oleg Schildt
 */

/**
 * This is the namespace SmartFactory.
 *
 * @author Oleg Schildt
 */
namespace SmartFactory;

/**
 * Records a timestamp each time an object is touched.
 *
 * @author Oleg Schildt
 */
trait TimestampsTrait
{
    /**
     * The most recent timestamp recorded by `touch()`, or null before the first call.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    private ?string $createdAt = null;

    /**
     * Replaces the stored timestamp with the current local date and time.
     *
     * @return void
     * Stores the timestamp in `Y-m-d H:i:s` format using PHP's default time zone.
     *
     * @author Oleg Schildt
     */
    public function touch(): void
    {
        $this->createdAt = date('Y-m-d H:i:s');
    } // touch

    /**
     * Reads the most recent timestamp recorded by `touch()`.
     *
     * @return string|null
     * Returns the timestamp in `Y-m-d H:i:s` format, or null before the first call to `touch()`.
     *
     * @author Oleg Schildt
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    } // getCreatedAt
} // TimestampsTrait

/**
 * Demonstrates timestamp recording for a booking.
 *
 * @author Oleg Schildt
 */
class BookingOverTrait
{
    use TimestampsTrait;
} // Booking

/**
 * Demonstrates timestamp recording for a voucher.
 *
 * @author Oleg Schildt
 */
class VoucherOverTrait
{
    use TimestampsTrait;
} // Voucher
