<?php
namespace Icecave\Recoil\Channel\Stream;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
use Icecave\Recoil\Channel\ReadableChannelInterface;
use Icecave\Recoil\Recoil;
use React\Stream\ReadableStreamInterface;

class ReadableStreamChannel implements ReadableChannelInterface
{
    public function __construct(ReadableStreamInterface $stream)
    {
        $this->stream = $stream;

        $this->stream->on('data',  [$this, 'onStreamData']);
        $this->stream->on('end',   [$this, 'onStreamEnd']);
        $this->stream->on('error', [$this, 'onStreamError']);

        // Do not read any data until a co-routine suspends waiting for data.
        $this->stream->pause();
    }

    /**
     * Read from this channel.
     *
     * @coroutine
     *
     * @return mixed                            The value read from the channel.
     * @throws Exception\ChannelClosedException if the channel has been closed.
     */
    public function read()
    {
        if ($this->isClosed()) {
            throw new ChannelClosedException($this);
        } elseif ($this->readStrand) {
            throw new ChannelLockedException($this);
        }

        $value = (yield Recoil::suspend(
            function ($strand) {
                $this->readStrand = $strand;
                $this->stream->resume();
            }
        ));

        yield Recoil::return_($value);
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * Close this channel.
     *
     * @coroutine
     */
    public function close()
    {
        $this->stream->close();

        yield Recoil::noop();
    }

    /**
     * Check if this channel is closed.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return !$this->stream->isReadable();
    }

   /**
     * @param string                  $data
     * @param ReadableStreamInterface $stream
     */
    public function onStreamData($data, ReadableStreamInterface $stream)
    {
        $this->stream->pause();

        if ($data) {
            $this->readStrand->resumeWithValue($data);
            $this->readStrand = null;
        }
    }

    /**
     * @param ReadableStreamInterface $stream
     */
    public function onStreamEnd(ReadableStreamInterface $stream)
    {
        $this->stream->removeListener('data',  [$this, 'onStreamData']);
        $this->stream->removeListener('end',   [$this, 'onStreamEnd']);
        $this->stream->removeListener('error', [$this, 'onStreamError']);

        if ($this->readStrand) {
            $this->readStrand->resumeWithException(
                new ChannelClosedException($this)
            );
            $this->readStrand = null;
        }
    }

    /**
     * @param Exception               $exception
     * @param ReadableStreamInterface $stream
     */
    public function onStreamError(Exception $exception, ReadableStreamInterface $stream)
    {
        $this->readStrand->resumeWithException($exception);
        $this->readStrand = null;
    }

    private $stream;
    private $readStrand;
}
