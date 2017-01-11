<?php
namespace QueueSystem\SQS;

class SleepTimer
{
    //Formula to use for Polling
    const POLL_TYPE_MOF = "MULTIPLE_OF";
    //Tolerence before moving to next level.
    const TOLERANCE = 2;
    //Default formula to use.
    const DEF_POLL_FORMULA = "MULTIPLE_OF";
    //Default variant.
    const DEF_VARIANT = 2;
    
    //Configured formula for this object.
    private $pollFormula = NULL;
    //Configured variant. 
    private $variant = 2;
    //Max limit.
    private $maxSleepTime = 10; //seconds
    //Current sleep value.
    private $currentsleep = NULL; //seconds
    //Current tolerated value.
    private $tolerated = 0;

    /**
     *  Constructor object for SleepTimer.
     * 
     * @param string $formula
     * @param int $variant
     */
    public function __construct($formula = self::DEF_POLL_FORMULA, $variant = self::DEF_VARIANT)
    {
        $this->pollFormula = empty($formula)? self::DEF_POLL_FORMULA : $formula;
        $this->variant = empty($variant)? self::DEF_VARIANT : $variant;
        $this->resetSleepTimer();
    }
    
    /**
     * Use multipleOf logic to get the new value of sleep.
     *  
     * @param int $value
     * @return int
     */
    private function multipleOf($value)
    {
        if ($this->currentsleep == NULL) {
            //this is the first time we have come here.
            $this->currentsleep = 1;
            return;
        }
        if ($this->currentsleep >= $this->maxSleepTime) {
            //dont go beyond this point.
            return;
        }
        if ($this->tolerated == self::TOLERANCE) {
            $this->currentsleep += $value;
            $this->tolerated = 1;
            return;
        }
        $this->tolerated++;
    }
    
    /**
     * Reset timer to original value.
     */
    public function resetSleepTimer() {
        $this->currentsleep = NULL;
        $this->tolerated = 0;
    }

    /**
     * Get Sleep time.
     * 
     * @return int
     */
    public function getSleepTime()
    {
        switch ($this->pollFormula) {
            default:
                $this->multipleOf($this->variant);
        }
        return $this->currentsleep;
    }
}
