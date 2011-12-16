<?php
/**
 * @file
 * The Transport class.
 */
namespace HPCloud;
/**
 * Provide underlying transportation logic.
 *
 * Interaction with the OpenStack/HPCloud services is handled via
 * HTTPS/REST requests. This class provides transport for requests, with
 * a simple and abstracted interface for issuing requests and processing
 * results.
 *
 */
class Transport {

  /**
   * Construct a new transport.
   */
  public function __construct() {

    // Need to know what transporter we're using.
  }

  /**
   * Handle an HTTP GET request.
   */
  public function doGet() {
  }

  /**
   * Handle an HTTP POST request.
   */
  public function doPost() {
  }
  /**
   * Handle an HTTP PUT request.
   */
  public function doPut() {
  }
  /**
   * Handle an HTTP DELETE request.
   */
  public function doDelete() {
  }
  /**
   * Handle an HTTP HEAD request.
   */
  public function doHead() {
  }


}

// Farm transport encoding to a separate class?
// class TransportEncoder{}
