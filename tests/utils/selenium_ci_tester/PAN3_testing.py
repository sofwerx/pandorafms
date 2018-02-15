# -*- coding: utf-8 -*-
from include.common_classes_60 import PandoraWebDriverTestCase
from include.common_functions_60 import login, click_menu_element, refresh_N_times_until_find_element, gen_random_string
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import Select
from selenium.common.exceptions import StaleElementReferenceException
import unittest2, time, re


class PAN3(PandoraWebDriverTestCase):

	test_name = u'PAN_3'
	test_description = u'Creates a simple ICMP check against localhost and checks the result is 1'
	tickets_associated = []

	def test_pan3(self):
		driver = self.driver
		login(driver,"admin","pandora",self.base_url)
		click_menu_element(driver,"Agent detail")
		driver.find_element_by_id("submit-crt").click()
		driver.find_element_by_id("text-agente").click()
		driver.find_element_by_id("text-agente").clear()
		driver.find_element_by_id("text-agente").send_keys(gen_random_string(10,preffix="PAN3_"))
		driver.find_element_by_id("text-direccion").click()
		driver.find_element_by_id("text-direccion").clear()
		driver.find_element_by_id("text-direccion").send_keys("127.0.0.1")
		driver.find_element_by_id("submit-crtbutton").click()
		driver.find_element_by_css_selector("li.nomn.tab_godmode > a > img.forced_title").click()
		driver.find_element_by_id("moduletype").click()
		Select(driver.find_element_by_id("moduletype")).select_by_visible_text("Create a new network server module")
		driver.find_element_by_name("updbutton").click() #Alternative XPATH: //*[@class="datos"]/input
		driver.find_element_by_name("updbutton").click() #IMPORTANT! It's needed to click TWICE! One for leave the combo, and other for clicking the button
		driver.find_element_by_id("id_module_type").click()
		combo = driver.find_element_by_id("id_module_type")
		Select(combo).select_by_visible_text("Remote ICMP network agent, boolean data")
		combo.click()
		driver.find_element_by_id("text-name").clear()
		driver.find_element_by_id("text-name").send_keys("ping test")
		driver.find_element_by_id("submit-crtbutton").click()
		driver.find_element_by_xpath('//*[@id="menu_tab"]//a[contains(@href,"ver_agente")]').click()

		max_retries = 3
		i = 1
		element_text = ""

		while (i <= max_retries): # Temporary workaround to weird StaleElementReferenceException exceptions due Javascript altering the DOM
			try:
				element_text = refresh_N_times_until_find_element(driver,5,"table1-1-7",how=By.ID).text
				self.assertEqual("1", element_text.lstrip().rstrip()) # The lstrip.rstrip is done because if not, this error is raised: "'1' != u'1 '"
				print "found it"
				break
			except StaleElementReferenceException as e_stale:
				i = i+1
				print "found stale"
				if i > max_retries:
					print "giving up"
                                        self.verificationErrors.append(str(e_stale))
					break
                                else:
					print "retrying"
                                        next
			except AssertionError as e:
				print "assertion error"
				self.verificationErrors.append(str(e))
				break

if __name__ == "__main__":
	unittest2.main()

