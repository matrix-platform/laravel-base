<?php //>

namespace MatrixPlatform\Mail;

use Illuminate\Mail\Mailable;

class MessageMail extends Mailable {

    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $fromAddress = '',
        public string $fromName = ''
    ) {}

    public function build(): static {
        $this->subject($this->subjectLine)->html($this->body);

        if ($this->fromAddress !== '') {
            $this->from($this->fromAddress, $this->fromName);
        }

        return $this;
    }

}
