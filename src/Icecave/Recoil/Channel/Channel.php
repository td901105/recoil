<?php
namespace Icecave\Recoil\Channel;

use Icecave\Recoil\Recoil;

/**
 * An unbuffered data channel that allows at most one concurrent read/write
 * operation.
 */
class Channel implements ReadableChannelInterface, WritableChannelInterface
{
    public function __construct()
    {
        $this->closed = false;
    }

    /**
     * Read from this channel.
     *
     * @coroutine
     *
     * @return mixed                            The value read from the channel.
     * @throws Exception\ChannelClosedException if the channel has been closed.
     * @throws Exception\ChannelLockedException if the channel is locked.
     */
    public function read()
    {
        if ($this->isClosed()) {
            throw new Exception\ChannelClosedException($this);
        } elseif ($this->readStrand) {
            throw new Exception\ChannelLockedException($this);
        }

        $value = (yield Recoil::suspend(
            function ($strand) {
                $this->readStrand = $strand;

                if ($this->writeStrand) {
                    $this->writeStrand->resumeWithValue(null);
                    $this->writeStrand = null;
                }
            }
        ));

        yield Recoil::return_($value);
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * Write to this channel.
     *
     * @coroutine
     *
     * @param  mixed                            $value The value to write to the channel.
     * @throws Exception\ChannelClosedException if the channel has been closed.
     * @throws Exception\ChannelLockedException if the channel is locked.
     */
    public function write($value)
    {
        if ($this->isClosed()) {
            throw new Exception\ChannelClosedException($this);
        } elseif ($this->writeStrand) {
            throw new Exception\ChannelLockedException($this);
        }

        if (!$this->readStrand) {
            yield Recoil::suspend(
                function ($strand) {
                    $this->writeStrand = $strand;
                }
            );
        }

        $this->readStrand->resumeWithValue($value);
        $this->readStrand = null;
    }

    /**
     * Close this channel.
     *
     * @coroutine
     */
    public function close()
    {
        $this->closed = true;

        if ($this->writeStrand) {
            $this->writeStrand->resumeWithException(
                new Exception\ChannelClosedException($this)
            );
            $this->writeStrand = null;
        }

        if ($this->readStrand) {
            $this->readStrand->resumeWithException(
                new Exception\ChannelClosedException($this)
            );
            $this->readStrand = null;
        }

        yield Recoil::noop();
    }

    /**
     * Check if this channel is closed.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return $this->closed;
    }

    private $closed;
    private $readStrand;
    private $writeStrand;
}
