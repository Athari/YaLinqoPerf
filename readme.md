# *YaLinqoPerf: Benchmarks for YaLinqo, Ginq and Pinq*

https://github.com/Athari/YaLinqoPerf

About
=====

Performance tests of libraries implementing LINQ in PHP. Only full-featured high-quality libraries are included: *YaLinqo*, *Ginq* and *Pinq*. The following cases are covered, where possible:

* *raw PHP* using `for` and `foreach` cycles
* *raw PHP* using array functions and closures
* *YaLinqo* using closures
* *YaLinqo* using string lambdas
* *Ginq* using closures
* *Ginq* using property accessors
* *Pinq* using closures

Other librararies are garbage and aren't worth using: *LINQ for PHP*, *Phinq*, *PHPLinq* and *Plinq* (see links to articles below for more information).

Libraries not implementing LINQ aren't included in this test: *Underscore.php*.

Results
=======

See [Gist](https://gist.github.com/Athari/1d001fde76f86f219c23) (PHP 5.5.14 on Windows 7 SP1 64 bit).

Links
=====

##### Articles

* **CodeProject** article *(English):* [LINQ for PHP comparison: YaLinqo, Ginq, Pinq](http://www.codeproject.com/Articles/997238/LINQ-for-PHP-comparison-YaLinqo-Ginq-Pinq).
* **Habrahabr** article *(Russian):* [LINQ for PHP: speed matters](http://habrahabr.ru/post/259155/).

##### Libraries

* [**YaLinqo**](https://github.com/Athari/YaLinqo)
* [**Ginq**](https://github.com/akanehara/ginq)
* [**Pinq**](https://github.com/TimeToogo/Pinq)
