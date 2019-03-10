CHANGELOG
=========

0.4.1 (2019-03-10)
------------------
* More performance optimizations in the encoding / decoding process for large amounts of data structures.

0.4.0 (2019-03-03)
------------------
* Performance and memory improvements.
* Removed the 'trailing data' aspect of decoded types.
* Added a 'getLastPosition' method for encoders. Returns the last position in the byte stream the decoder stopped at.
* Removed the 'constructed_only' and 'primitive_only' options for the encoder.

0.3.1 (2019-01-21)
------------------
* Additional minor performance improvements.

0.3.0 (2019-01-20)
------------------
* Improve general encoder performance with various optimizations.
* Add arbitrary precision support for tag numbers.
* Add arbitrary precision support for OID types.
* Remove the TypeFactory. Do not load classes dynamically.
* Simplify long definite length decoding.
* Simplify VLQ decoding to be a single operation.

0.2.0 (2018-09-16)
------------------
* Support arbitrary-precision for Integer/Enumerated types with the GMP extension.

0.1.2 (2018-04-15)
------------------
* Fix option handling for current set of options.

0.1.1 (2018-04-15)
------------------
* Add an options setter / getter. Merge options recursively.

0.1.0 (2018-04-14)
------------------
* Initial release.
