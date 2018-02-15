#!/usr/bin/env python
from unittest2 import *
from console.include.common_functions_60 import *
from console.include.common_classes_60 import *
from pyvirtualdisplay import Display
from os import environ, getenv
import subprocess, time, sys

def get_test_file(test_list):
	#return [test[0].split(' ')[0].split('.')[0].split('<')[1] for test in test_list]
	return [test[0].test_name for test in test_list]

# Are we running headless?
if ('DISPLAY' not in os.environ):
    display = Display(visible=0, size=(1920, 1080))
    display.start()

#Run Enterprise tests
is_enterprise = '1' == getenv('ENTERPRISE', False)

a = TestLoader()

tests = a.discover(start_dir='console',pattern='*.py')

c = ArticaTestResult()
tests.run(c)

#sauce_client = SauceClient(environ["SAUCE_USERNAME"], environ["SAUCE_ACCESS_KEY"])
#for test,error_msg in c.failures+c.errors:
#	try:
#		sauce_client.jobs.update_job(test.sauce_labs_job_id, passed=False,tags=[environ["TRAVIS_BRANCH"],test.id()],build_num=environ["TRAVIS_JOB_NUMBER"],name=str(test.id().split('.')[1]+': '+test.id().split('.')[2]))
#	except:
#		print "Could not annotate Sauce Labs job #%s" % str(test)
#		next
#
#for test,error_msg in c.success+c.skipped:
#	try:
#		sauce_client.jobs.update_job(test.sauce_labs_job_id, passed=True,tags=[environ["TRAVIS_BRANCH"],test.id()],build_num=environ["TRAVIS_JOB_NUMBER"],name=str(test.id().split('.')[1]+': '+test.id().split('.')[2]))
#
#	except:
#                print "Could not annotate Sauce Labs job #%s" % str(test)
#                next
#Update Saouce Labs jobs
	
if ('DISPLAY' not in os.environ):
    display.stop()

print "Tests failed: %s" % c.failures
print "Tests succeeded: %s" % c.success
print "Tests skipped: %s" % c.skipped
print "Tests with errors: %s" % c.errors

if (len(c.failures)+len(c.errors)) != 0:
	sys.exit(1)

else:
	sys.exit(0)
