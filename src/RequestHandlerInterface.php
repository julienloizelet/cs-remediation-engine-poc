<?php


interface RequestHandlerInterface
{
    /**
     * Performs an HTTP request and returns a response.
     */
    public function handle(Request $request): Response;
}
