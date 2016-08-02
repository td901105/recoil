<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Exception\KernelPanicException;
use Recoil\Kernel\Exception\KernelStoppedException;
use Throwable;

interface Kernel extends Listener
{
    /**
     * Run the kernel until all strands exit, the kernel is stopped or a kernel
     * panic occurs.
     *
     * A kernel panic occurs when a strand throws an exception that is not
     * handled by the kernel's exception handler.
     *
     * This method returns immediately if the kernel is already running.
     *
     * @see Kernel::setExceptionHandler()
     *
     * @return null
     * @throws KernelPanicException A strand has caused a kernel panic.
     */
    public function run();

    /**
     * Stop the kernel.
     *
     * Stopping the kernel causes all calls to {@see Kernel::executeSync()}
     * or {@see Kernel::adoptSync()} to throw a {@see KernelStoppedException}.
     *
     * The kernel cannot run again until it has stopped completely. That is,
     * the PHP call-stack has unwound to the outer-most call to {@see Kernel::run()},
     * {@see Kernel::executeSync()} or {@see Kernel::adoptSync()}.
     *
     * @return null
     */
    public function stop();

    /**
     * Schedule a coroutine for execution on a new strand.
     *
     * Execution begins when the kernel is started; or, if called within a
     * strand, when that strand cooperates.
     *
     * @param mixed $coroutine The coroutine to execute.
     */
    public function execute($coroutine) : Strand;

    /**
     * Execute a coroutine on a new strand and block until it exits.
     *
     * If the kernel is not running, it is run until the strand exits, the
     * kernel is stopped explicitly, or a different strand causes a kernel panic.
     *
     * The kernel's exception handler is bypassed for this strand. Instead, if
     * the strand produces an exception it is re-thrown by this method.
     *
     * This is a convenience method equivalent to:
     *
     *      $strand = $kernel->execute($coroutine);
     *      $kernel->adoptSync($strand);
     *
     * @param mixed $coroutine The coroutine to execute.
     *
     * @return mixed                  The return value of the coroutine.
     * @throws Throwable              The exception produced by the coroutine.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException The kernel was stopped before the strand exited.
     * @throws KernelPanicException   Some other strand has caused a kernel panic.
     */
    public function executeSync($coroutine);

    /**
     * Block until a strand exits.
     *
     * If the kernel is not running, it is run until the strand exits, the
     * kernel is stopped explicitly, or a different strand causes a kernel panic.
     *
     * The kernel's exception handler is bypassed for this strand. Instead, if
     * the strand produces an exception it is re-thrown by this method.
     *
     * @param Strand $strand The strand to wait for.
     *
     * @return mixed                  The return value of the coroutine.
     * @throws Throwable              The exception produced by the coroutine.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException The kernel was stopped before the strand exited.
     * @throws KernelPanicException   Some other strand has caused a kernel panic.
     */
    public function adoptSync(Strand $strand);

    /**
     * Set a user-defined exception handler function.
     *
     * The exception handler function is invoked when a strand exits with an
     * unhandled exception. That is, whenever an exception propagates to the top
     * of the strand's call-stack and the strand does not already have a
     * mechanism in place to deal with the exception.
     *
     * The exception handler function must have the following signature:
     *
     *      function (Strand $strand, Throwable $exception)
     *
     * The first parameter is the strand that produced the exception, the second
     * is the exception itself.
     *
     * A kernel panic is caused if the handler function throws an exception, or
     * there is no handler installed when a strand fails.
     *
     * @param callable|null $fn The exception handler (null = remove).
     *
     * @return null
     */
    public function setExceptionHandler(callable $fn = null);
}
