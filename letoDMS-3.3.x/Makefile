VERSION=3.3.12
SRC=CHANGELOG* inc conf utils index.php languages op out README README.Notification reset_db.sql drop-tables-innodb.sql delete_all_contents.sql styles js TODO LICENSE Makefile webdav install

dist:
	mkdir -p tmp/letoDMS-$(VERSION)
	cp -a $(SRC) tmp/letoDMS-$(VERSION)
	(cd tmp; tar --exclude=.svn -czvf ../LetoDMS-$(VERSION).tar.gz letoDMS-$(VERSION))
	rm -rf tmp

pear:
	(cd LetoDMS_Core/; pear package)
	(cd LetoDMS_Lucene/; pear package)

webdav:
	mkdir -p tmp/letoDMS-webdav-$(VERSION)
	cp webdav/* tmp/letoDMS-webdav-$(VERSION)
	(cd tmp; tar --exclude=.svn -czvf ../LetoDMS-webdav-$(VERSION).tar.gz letoDMS-webdav-$(VERSION))
	rm -rf tmp

doc:
	phpdoc -d LetoDMS_Core --ignore 'getusers.php,getfoldertree.php,config.php,reverselookup.php' -t html

.PHONY: webdav
