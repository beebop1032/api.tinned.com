<?php echo "<?php\n"; ?>

namespace App\<?php echo $namespace; ?>;

class <?php echo $className; ?> extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 404,
        \Throwable $prev = null
    ) {
        parent::__construct($message, $code, $prev);
    }
}
