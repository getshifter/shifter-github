## How to update your plugin/theme.

To release the new version, please do as follows:

### Tag and push to GitHub, then upload the package by one of the ways as follows.

```
$ git tag 1.1.0
$ git push origin 1.1.0
```

- `1.1.0` is a version number, it has to be same with version number in your WordPress plugin.
- You have to commit `vendor` directory in your plugin.

### A. Manually release the new version.

1. Please visit "**releases**" in your GitHub repository.
1. Choose a tag.
1. Fill out the release note and title.
1. Upload your plugin which is comporessed with zip. (Optional)
1. Press "Publish release".

### B. Automated release the new version with GitHub Actions.

You can upload the package automat GutHub Actions.

As for now, GitHub Actions service is in beta, so you have to [sign up the Beta here](https://github.com/features/actions)

>When the service is ready you will find the Actions tab on the top page of your repository, then follow the steps below:

1. Then, copy the .github directory on the below url to the root directory of your local repository. [getshifter/shifter-github-hosting-plugin-sample](https://github.com/getshifter/shifter-github-hosting-plugin-sample)
1. It must be look like: /path_to_your_repository/.github/workflows/release.yml
1. Open the release.yml file with your favorite editor and change some lines:
    - You MUST change the PACKAGE_NAME to your package name.
    - Also, if you need:
        - change FILES_TO_ARCIVE
        - comment out the composer install section
        - uncomment npm install section
1. After changed some lines, commit and push it to your GitHub repository.

Now when you push your tag to GitHub, the release package will be created automatically.

#### Example Projects

Please install old version of following projects, then you can see update notice.

- [getshifter/shifter-github-hosting-plugin-sample](https://github.com/getshifter/shifter-github-hosting-plugin-sample)
- [getshifter/shifter-github-hosting-theme-sample](https://github.com/getshifter/shifter-github-hosting-theme-sample)

These projects deploy new releases automatically with GitHub Actions.

Please check [.github/workflows/release.yml](https://github.com/getshifter/shifter-github-hosting-plugin-sample/blob/master/.github/workflows/release.yml)

### C. Automated release the new version with Travis.

Also, you can use [Automatic release](https://docs.travis-ci.com/user/deployment/releases/) with Travis.

Following is an example of the `.travis.yml` for automatic release.

[https://github.com/miya0001/miya-gallery/blob/master/.travis.yml](https://github.com/miya0001/miya-gallery/blob/master/.travis.yml)

You can generate `deploy:` section by [The Travis Client](https://github.com/travis-ci/travis.rb) like following.

```
$ travis setup releases
```
