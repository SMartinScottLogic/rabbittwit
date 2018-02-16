#include <cstdio>
#include <cstring>
#include <cerrno>
#include <sys/stat.h>
#include <sys/types.h>
#include <dirent.h>

#include <vector>
#include <set>
#include <string>
#include <algorithm>

std::set<std::string> skip_paths;
bool g_case_insensitive = false;

struct file {
  std::string key;
  std::string name;
  std::string path;

  file(const std::string &_name, const std::string &_path, bool case_insensitive=false)
  : key(_name), name(_name), path(_path) {
    if(case_insensitive) {
      std::transform(key.begin(), key.end(), key.begin(), tolower);
    }
  }

  bool operator<(const file& b) const {
    if(key < b.key) return true;
    if(key == b.key) {
      return (path < b.path);
    }
    return false;
  }
};

std::vector<file> files;

void dots() {
  if( (files.size() % 10000)==0) {
    fprintf(stderr, "\n%zu ", files.size() );
  }
  if( (files.size() % 100) == 0) {
    fprintf(stderr, ".");
  }
}

void fetchfiles(bool recursive, const std::string &dir) {
  DIR *dirp = opendir( dir.c_str() );
  struct dirent *dp = NULL;
  while ((dp = readdir(dirp)) != NULL) {
    if( (dp->d_type & DT_REG)==DT_REG) {
      dots();
      files.push_back( file(dp->d_name, dir, g_case_insensitive) );
    }
    if( recursive && (dp->d_type & DT_DIR)==DT_DIR && dp->d_name[0]!='.' && skip_paths.find(dp->d_name)==skip_paths.end() ) {
      fetchfiles(recursive, dir + "/" + dp->d_name);
    }
  }
  closedir(dirp);
}

void write_box(const std::vector<file> &box, const std::string &prefix, size_t &box_id) {
  char dirname[1024];
  bool success = false;
  while(success != true) {
    sprintf(dirname, "%s_%zu", prefix.c_str(), box_id);
    if(mkdir(dirname, 0777)==-1) {
      success = false;

      if(errno!=EEXIST) {
        fprintf(stderr, "ERROR(mkdir '%s'): %d: %s.\n", dirname, errno, strerror(errno));
        exit(-1);
      }
      box_id ++;
    } else {
      success = true;
    }
  }
  for(std::vector<file>::const_iterator it=box.begin(); it!=box.end(); ++it) {
    std::string source = it->path + "/" + it->name;
    std::string target = std::string(dirname) + "/" + (it->name);
    printf( "%s -> %s\n", source.c_str(), target.c_str() );
    if(rename(source.c_str(), target.c_str() )==-1) {
      fprintf(stderr, "rename('%s', '%s'): %d: %s.\n", source.c_str(), dirname, errno, strerror(errno));
      exit(-1);
    }
  }
}

void boxfiles(const std::vector<file> &source, const std::string &prefix, size_t threshold) {
  size_t box_id = 1;
  std::vector<file> box;
  for(std::vector<file>::const_iterator it=source.begin(); it!=source.end(); ++it) {
    if(box.size()>=threshold) {
      write_box(box, prefix, box_id);
      box.clear();
      box_id++;
    }
    box.push_back(*it);
  }
  if(box.size()>0) {
    write_box(box, prefix, box_id);
  }
}

int main(int argc, char *argv[]) {
  size_t max_files = 100;
  std::string prefix = "GROUP";
  bool recursive = false;

  int c = -1;
  while((c=getopt(argc, argv, "irm:p:x:"))!=-1) {
    switch(c) {
      case 'i':
        g_case_insensitive = true;
        break;
      case 'r':
        recursive = true;
        break;
      case 'm':
        sscanf(optarg, "%zu", &max_files);
        break;
      case 'p':
        prefix = optarg;
        break;
      case 'x':
        skip_paths.insert(optarg);
        break;
    }
  }
  printf( "Max files: %zu\n", max_files);
  printf( "Prefix   : '%s'\n", prefix.c_str() );
  printf( "Recursive: %s\n", recursive?"TRUE":"FALSE" );

  printf( "Splitting with a threshold of %zu\n", max_files );
  fetchfiles( recursive, "." );
  printf( "Found %zu files.\n", files.size() );
  printf( "Sorting..." );
  std::sort(files.begin(), files.end());
  printf( "done.\n");

  /*
  for(std::vector<file>::const_iterator it=files.begin(); it!=files.end(); ++it) {
    printf( "%s %s\n", it->path.c_str(), it->name.c_str() );
  }
  */
  //splitfiles(files, "", max_files);
  boxfiles(files, prefix, max_files);
}
