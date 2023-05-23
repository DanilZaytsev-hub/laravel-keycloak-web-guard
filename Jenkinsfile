pipeline{
    agent{
        label "node-1"
    }
    environment {
        GITEA_API_TOKEN = credentials('gitea-token-crd')
    }
    stages {
        stage("Create archive .zip TEST") {
            steps{
                sh 'zip -r ${JOB_NAME}.zip .'
            }
            post{
                success {
                    echo "======== Archive created successfully ========"
                }
                failure {
                    echo "======== Archive created failed ========"
                }
            }
        }
        stage("Push archive in gitea repository") {
            steps {
                sh '''curl --user jenkins:$GITEA_API_TOKEN \
                    --upload-file ${JOB_NAME}.zip \
                    https://git.danaflex-nano.ru/api/packages/Danaflex/composer?version=0.${BUILD_NUMBER}'''
            }
        }
    }
}
