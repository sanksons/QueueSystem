<?php

namespace QueueSystem;

class WorkerPool extends \Jenner\SimpleFork\FixedPool
{
	/**
	 * Check if any job is pending to be completed.
	 * 
	 * @return boolean
	 */	
	public function isAnyJobPending() {
		return (count($this->processes) > 0) ? true:false;
	}

	/**
	 * Check if we need to break out of the wait().
	 * 
	 * @param int $pendingJobsCount
	 * @return boolean
	 */
    private function intentionalBreak($pendingJobsCount)
    {
        if ($pendingJobsCount < $this->max) {
            //clean old jobs
            foreach ($this->processes as $key => $process) {
                if ($process->isStopped()) {
                    unset($this->processes[$key]);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Start more jobs.
     * 
     * @return int => no. of jobs pending.
     */
    public function allotMoreJobs()
    {
        $pending = 0;
        foreach ($this->processes as $process) {
            if ($process->hasStarted() || $process->isStopped() || $process->isRunning()) {
                continue;
            }
            if ($this->aliveCount() < $this->max) {
                $process->start();
                continue;
            }
            $pending++;
        }
        return $pending;
    }

    /**
     * wait for all process done.
     *
     * @param bool $block block the master process
     * to keep the sub process count all the time
     * @param int $interval check time interval
     */
    public function wait($block = false, $interval = 10000)
    {   
        do {
            if (!$this->isAnyJobPending()) {
                return;
            }
            if ($this->aliveCount() < $this->max) {
            	$pending = $this->allotMoreJobs();
                if ($this->intentionalBreak($pending)) {
                	return;
                }
            }
            $block ? usleep($interval) : null;
        } while ($block);
    }
    
}
